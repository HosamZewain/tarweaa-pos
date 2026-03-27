<?php

namespace App\Services;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\MenuItem;
use App\Models\MenuItemModifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCreationService
{
    public function __construct(
        private readonly DiscountAuditService $discountAuditService,
        private readonly RecipeInventoryService $recipeInventoryService,
        private readonly RecipeService $recipeService,
    ) {}

    /**
     * Create a new empty order after validating all prerequisites.
     *
     * @throws OrderException
     */
    public function create(User $cashier, CreateOrderData $data): Order
    {
        if (!$cashier->is_active) {
            throw OrderException::cashierInactive();
        }

        $hasDiscount = $data->discountType !== null || $data->discountValue > 0;
        if ($hasDiscount && !$cashier->hasPermission('apply_discount')) {
            throw OrderException::discountPermissionRequired();
        }

        $guardRow = CashierActiveSession::with([
            'drawerSession.shift',
            'drawerSession.posDevice',
        ])->find($cashier->id);

        if (!$guardRow) {
            throw OrderException::noOpenDrawer();
        }

        $drawerSession = $guardRow->drawerSession;
        $shift         = $drawerSession->shift;
        $posDevice     = $drawerSession->posDevice;

        if (!$shift->isOpen()) {
            throw OrderException::noActiveShift();
        }

        if ($data->type->requiresDeliveryAddress() && empty($data->deliveryAddress)) {
            throw OrderException::deliveryAddressRequired();
        }

        return DB::transaction(function () use ($cashier, $data, $shift, $drawerSession, $posDevice): Order {
            $order = Order::create([
                'order_number'           => Order::generateOrderNumber(),
                'type'                   => $data->type,
                'status'                 => OrderStatus::Pending,
                'source'                 => $data->source,
                'cashier_id'             => $cashier->id,
                'shift_id'               => $shift->id,
                'drawer_session_id'      => $drawerSession->id,
                'pos_device_id'          => $posDevice->id,
                'customer_id'            => $data->customerId,
                'customer_name'          => $data->customerName,
                'customer_phone'         => $data->customerPhone,
                'delivery_address'       => $data->deliveryAddress,
                'delivery_fee'           => $data->deliveryFee,
                'discount_type'          => $data->discountType,
                'discount_value'         => $data->discountValue,
                'tax_rate'               => $data->taxRate,
                'subtotal'               => 0,
                'discount_amount'        => 0,
                'tax_amount'             => 0,
                'total'                  => 0,
                'payment_status'         => PaymentStatus::Unpaid,
                'paid_amount'            => 0,
                'change_amount'          => 0,
                'refund_amount'          => 0,
                'notes'                  => $data->notes,
                'scheduled_at'           => $data->scheduledAt,
                'external_order_id'      => $data->externalOrderId,
                'external_order_number'  => $data->externalOrderNumber,
                'created_by'             => $cashier->id,
                'updated_by'             => $cashier->id,
            ]);

            Log::info('Order created', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'cashier_id'   => $cashier->id,
                'shift_id'     => $shift->id,
                'type'         => $data->type->value,
                'source'       => $data->source->value,
            ]);

            return $order;
        });
    }

    /**
     * Add a menu item (with optional variant and modifiers) to an order.
     *
     * @throws OrderException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function addItem(Order $order, AddOrderItemData $data, int $actorId): OrderItem
    {
        if ($order->status->isFinal()) {
            throw OrderException::invalidTransition($order->status, $order->status);
        }

        if ($data->quantity < 1) {
            throw OrderException::itemQtyInvalid();
        }

        $menuItem = MenuItem::with(['variants'])->findOrFail($data->menuItemId);

        if (!$menuItem->is_available) {
            throw OrderException::itemNotAvailable($menuItem->name);
        }

        $variant = null;
        if ($data->variantId !== null) {
            $variant = $menuItem->variants->firstWhere('id', $data->variantId);

            if (!$variant) {
                throw OrderException::variantNotFound();
            }

            if (!$variant->is_available) {
                throw OrderException::itemNotAvailable("{$menuItem->name} — {$variant->name}");
            }
        } elseif ($menuItem->isVariable() && $menuItem->variants->isNotEmpty()) {
            $variant = $menuItem->variants->where('is_available', true)->first();
        }

        $modifierIds = array_keys($data->modifiers);
        $modifiers   = [];
        if (!empty($modifierIds)) {
            $modifiers = MenuItemModifier::whereIn('id', $modifierIds)
                                         ->where('is_available', true)
                                         ->get()
                                         ->keyBy('id');
        }

        return DB::transaction(function () use ($order, $data, $menuItem, $variant, $modifiers, $actorId): OrderItem {
            $snapshot = OrderItem::snapshotFrom($menuItem, $variant);

            $item = $order->items()->create([
                'menu_item_id'         => $menuItem->id,
                'menu_item_variant_id' => $variant?->id,
                'item_name'            => $snapshot['item_name'],
                'variant_name'         => $snapshot['variant_name'],
                'unit_price'           => $snapshot['unit_price'],
                'cost_price'           => $snapshot['cost_price'],
                'quantity'             => $data->quantity,
                'discount_amount'      => $data->discountAmount,
                'total'                => 0,
                'status'               => OrderItemStatus::Pending,
                'notes'                => $data->notes,
                'created_by'           => $actorId,
                'updated_by'           => $actorId,
            ]);

            foreach ($data->modifiers as $modifierId => $qty) {
                if ($modifier = $modifiers->get($modifierId)) {
                    $item->modifiers()->create([
                        'menu_item_modifier_id' => $modifier->id,
                        'modifier_name'         => $modifier->name,
                        'price'                 => $modifier->price,
                        'quantity'              => max(1, (int) $qty),
                    ]);
                }
            }

            $item->recalculate();
            $order->recalculate();

            $order->refresh();

            if ($order->discount_type !== null
                && (float) $order->discount_value > 0
                && $order->discountLogs()->where('scope', 'order')->doesntExist()
            ) {
                $this->discountAuditService->logOrderDiscount(
                    order: $order,
                    appliedBy: $actorId,
                    action: 'configured_on_create',
                    requestedBy: $order->cashier_id,
                );
            }

            $this->discountAuditService->logItemDiscount($item, $actorId);

            if ($this->recipeInventoryService->shouldDeductForOrder($order->fresh())) {
                $this->recipeInventoryService->deductForOrderItem($item, $actorId);
            }

            return $item->fresh(['modifiers']);
        });
    }

    /**
     * Create an order arriving from an external aggregator (Talabat, Jahez, etc.).
     *
     * @throws OrderException
     */
    public function createExternalOrder(
        User            $processedBy,
        CreateOrderData $data,
        int             $drawerSessionId,
    ): Order {
        if (!$data->source->isExternal()) {
            throw new \InvalidArgumentException('استخدم create() للطلبات الواردة من نقطة البيع.');
        }

        if (empty($data->externalOrderId)) {
            throw new \InvalidArgumentException('الطلبات الخارجية تتطلب رقم الطلب من المنصة الخارجية.');
        }

        $drawerSession = CashierDrawerSession::findOrFail($drawerSessionId);

        if (!$drawerSession->isOpen()) {
            throw OrderException::noOpenDrawer();
        }

        $shift = $drawerSession->shift;

        if (!$shift->isOpen()) {
            throw OrderException::noActiveShift();
        }

        return DB::transaction(function () use ($processedBy, $data, $drawerSession, $shift): Order {
            return Order::create([
                'order_number'           => Order::generateOrderNumber(),
                'type'                   => OrderType::Delivery,
                'status'                 => OrderStatus::Confirmed,
                'source'                 => $data->source,
                'cashier_id'             => $processedBy->id,
                'shift_id'               => $shift->id,
                'drawer_session_id'      => $drawerSession->id,
                'pos_device_id'          => $drawerSession->pos_device_id,
                'customer_name'          => $data->customerName,
                'customer_phone'         => $data->customerPhone,
                'delivery_address'       => $data->deliveryAddress,
                'delivery_fee'           => $data->deliveryFee,
                'tax_rate'               => $data->taxRate,
                'discount_type'          => $data->discountType,
                'discount_value'         => $data->discountValue,
                'notes'                  => $data->notes,
                'external_order_id'      => $data->externalOrderId,
                'external_order_number'  => $data->externalOrderNumber,
                'subtotal'               => 0,
                'discount_amount'        => 0,
                'tax_amount'             => 0,
                'total'                  => 0,
                'payment_status'         => PaymentStatus::Paid,
                'confirmed_at'           => now(),
                'created_by'             => $processedBy->id,
                'updated_by'             => $processedBy->id,
            ]);
        });
    }
}
