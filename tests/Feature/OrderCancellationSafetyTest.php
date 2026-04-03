<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\DTOs\ProcessPaymentData;
use App\Enums\DrawerSessionStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Exceptions\OrderException;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemRecipeLine;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\OrderCreationService;
use App\Services\OrderLifecycleService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
    }

    public function test_cancelling_paid_delivered_cash_order_reverses_stock_without_leaving_refund_cash_movement(): void
    {
        [$cashier, $order, $orderItem, $inventoryItem, $customer] = $this->createPaidRecipeOrder();

        $actor = User::where('email', 'admin@pos.com')->firstOrFail();
        $drawer = $order->drawerSession()->firstOrFail();

        $order->update([
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);

        $cancelled = app(OrderLifecycleService::class)->cancel($order->fresh(), $actor, 'طلب تجريبي بالخطأ');

        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $orderItem->refresh();
        $inventoryItem->refresh();
        $customer->refresh();

        $this->assertFalse($cancelled->trashed());
        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertSame(PaymentStatus::Refunded, $cancelled->payment_status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('360.00', number_format((float) $cancelled->refund_amount, 2, '.', ''));
        $this->assertNull($orderItem->stock_deducted_at);
        $this->assertSame('5.000', $inventoryItem->current_stock);
        $this->assertSame('5.000', $restaurantStock->current_stock);
        $this->assertSame(0, (int) $customer->total_orders);
        $this->assertSame('0.00', number_format((float) $customer->total_spent, 2, '.', ''));

        $this->assertDatabaseMissing('cash_movements', [
            'drawer_session_id' => $cancelled->drawer_session_id,
            'reference_type' => 'order',
            'reference_id' => $cancelled->id,
            'type' => 'refund',
            'amount' => '360.00',
        ]);
        $this->assertSame(100.0, $drawer->fresh()->calculateExpectedBalance());

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'type' => InventoryTransactionType::Return->value,
            'reference_type' => 'order_item',
            'reference_id' => $orderItem->id,
            'quantity' => '0.400',
        ]);
    }

    public function test_cancelling_paid_non_cash_order_requires_manual_external_reversal(): void
    {
        [$cashier, $order] = $this->createBasicPaidOrder(PaymentMethod::TalabatPay, 100, 'TAL-CANCEL-001');
        $actor = User::where('email', 'admin@pos.com')->firstOrFail();

        $order->update([
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('دفعات غير نقدية');

        app(OrderLifecycleService::class)->cancel($order->fresh(), $actor, 'لا يمكن إلغاؤه تلقائياً');
    }

    private function createPaidRecipeOrder(): array
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier Cancel Order',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier', 'is_active' => true],
        );
        $cashier->roles()->syncWithoutDetaching([$cashierRole->id]);

        $customer = \App\Models\Customer::create([
            'name' => 'عميل إلغاء',
            'phone' => '01000000002',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-CANCEL-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Cancel',
            'identifier' => 'POS-CANCEL-01',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-CANCEL-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now()->subHour(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'بطاطس إلغاء',
            'sku' => 'POT-CANCEL',
            'category' => 'خضار',
            'unit' => 'كجم',
            'unit_cost' => 50,
            'current_stock' => 5,
            'minimum_stock' => 1,
            'maximum_stock' => 10,
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'name' => 'ساندوتشات إلغاء',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'ساندوتش بطاطس',
            'type' => 'simple',
            'base_price' => 180,
            'is_available' => true,
            'is_active' => true,
        ]);

        MenuItemRecipeLine::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 200,
            'unit' => 'جم',
            'unit_conversion_rate' => 0.001,
        ]);

        $order = app(OrderCreationService::class)->create(
            cashier: $cashier,
            data: CreateOrderData::fromArray([
                'type' => OrderType::Takeaway->value,
                'source' => OrderSource::Pos->value,
                'customer_id' => $customer->id,
                'tax_rate' => 0,
            ]),
        );

        $orderItem = app(OrderCreationService::class)->addItem(
            order: $order,
            data: AddOrderItemData::fromArray([
                'menu_item_id' => $menuItem->id,
                'quantity' => 2,
            ]),
            actorId: $cashier->id,
        );

        app(OrderPaymentService::class)->processPayment(
            order: $order->fresh(),
            payments: [
                new ProcessPaymentData(method: PaymentMethod::Cash, amount: 360),
            ],
            actorId: $cashier->id,
        );

        return [$cashier, $order->fresh(), $orderItem->fresh(), $inventoryItem->fresh(), $customer->fresh()];
    }

    private function createBasicPaidOrder(PaymentMethod $paymentMethod, float $amount, ?string $reference = null): array
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier Basic Cancel',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier', 'is_active' => true],
        );
        $cashier->roles()->syncWithoutDetaching([$cashierRole->id]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-BASIC-CANCEL-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Basic Cancel',
            'identifier' => 'POS-BASIC-CANCEL-01',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-BASIC-CANCEL-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now()->subHour(),
        ]);

        $category = MenuCategory::create([
            'name' => 'وجبات إلغاء أساسي',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'وجبة إلغاء أساسية',
            'type' => 'simple',
            'base_price' => $amount,
            'cost_price' => 20,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-BASIC-CANCEL-001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'subtotal' => $amount,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => $amount,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'وجبة إلغاء أساسية',
            'unit_price' => $amount,
            'cost_price' => 20,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => $amount,
            'status' => \App\Enums\OrderItemStatus::Pending,
        ]);

        app(OrderPaymentService::class)->processPayment(
            order: $order->fresh(),
            payments: [
                new ProcessPaymentData(method: $paymentMethod, amount: $amount, referenceNumber: $reference),
            ],
            actorId: $cashier->id,
        );

        return [$cashier, $order->fresh(), $drawer];
    }
}
