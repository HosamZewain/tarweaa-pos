<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderLifecycleService
{
    public function __construct(
        private readonly DiscountAuditService $discountAuditService,
        private readonly DrawerSessionService $drawerSessionService,
    ) {}

    /**
     * Cancel a single order item and recalculate order totals.
     */
    public function removeItem(OrderItem $item, ?User $by = null): void
    {
        $order = $item->order;

        if ($by) {
            $this->drawerSessionService->assertSessionNotUnderReconciliation($order->drawerSession, $by);
        }

        if ($order->status->isFinal()) {
            throw OrderException::invalidTransition($order->status, $order->status);
        }

        DB::transaction(function () use ($item, $order): void {
            $item->update(['status' => OrderItemStatus::Cancelled]);
            $order->recalculate();
        });
    }

    /**
     * Apply or update an order-level discount and recalculate totals.
     */
    public function applyDiscount(
        Order $order,
        User $by,
        string $type,
        float $value,
        ?User $requestedBy = null,
        ?string $reason = null,
    ): Order
    {
        $this->drawerSessionService->assertSessionNotUnderReconciliation($order->drawerSession, $by);

        if ($order->status->isFinal()) {
            throw OrderException::invalidTransition($order->status, $order->status);
        }

        $previousDiscountAmount = (float) $order->discount_amount;

        DB::transaction(function () use ($order, $type, $value): void {
            $order->update([
                'discount_type'  => $type,
                'discount_value' => $value,
            ]);

            $order->recalculate();
        });

        $order = $order->fresh();

        $this->discountAuditService->logOrderDiscount(
            order: $order,
            appliedBy: $by->id,
            action: (float) $order->discount_amount <= 0 && $previousDiscountAmount > 0
                ? 'removed'
                : ($previousDiscountAmount > 0 ? 'updated' : 'applied'),
            previousDiscountAmount: $previousDiscountAmount,
            requestedBy: $requestedBy?->id ?? $by->id,
            reason: $reason,
        );

        return $order;
    }

    /**
     * Transition order to the next status in the workflow.
     *
     * @throws OrderException
     */
    public function transition(Order $order, OrderStatus $newStatus, User $by): Order
    {
        $this->drawerSessionService->assertSessionNotUnderReconciliation($order->drawerSession, $by);

        if (!$order->status->canTransitionTo($newStatus)) {
            throw OrderException::invalidTransition($order->status, $newStatus);
        }

        $order->transitionTo($newStatus, $by->id);

        return $order->fresh();
    }

    /**
     * Cancel an order.
     *
     * @throws OrderException
     */
    public function cancel(Order $order, User $by, string $reason): Order
    {
        $this->drawerSessionService->assertSessionNotUnderReconciliation($order->drawerSession, $by);

        if ($order->isCancelled()) {
            throw OrderException::alreadyCancelled();
        }

        if (!$order->isCancellable()) {
            throw OrderException::notCancellable($order->status);
        }

        return DB::transaction(function () use ($order, $by, $reason): Order {
            // Reverse any cash payments back into the drawer movement log
            if ((float) $order->paid_amount > 0) {
                $cashPaid = $order->payments()
                                  ->where('payment_method', PaymentMethod::Cash->value)
                                  ->sum('amount');

                if ($cashPaid > 0) {
                    $order->drawerSession->addMovement(
                        type:          CashMovementType::Refund,
                        amount:        $cashPaid,
                        performedBy:   $by->id,
                        referenceType: 'order',
                        referenceId:   $order->id,
                        notes:         "إلغاء طلب رقم {$order->order_number}",
                    );
                }
            }

            // Cancel all items
            $order->items()->update(['status' => OrderItemStatus::Cancelled]);

            $order->update([
                'status'              => OrderStatus::Cancelled,
                'cancelled_at'        => now(),
                'cancelled_by'        => $by->id,
                'cancellation_reason' => $reason,
                'updated_by'          => $by->id,
            ]);

            Log::info('Order cancelled', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'cancelled_by' => $by->id,
                'reason'       => $reason,
            ]);

            return $order->fresh();
        });
    }
}
