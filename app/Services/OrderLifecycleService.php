<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
        private readonly OrderReversalService $orderReversalService,
    ) {}

    /**
     * Cancel a single order item and recalculate order totals.
     */
    public function removeItem(OrderItem $item, ?User $by = null): void
    {
        $order = $item->order;

        if ($order->drawerSession?->isClosed()) {
            throw OrderException::drawerSessionClosed();
        }

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
        if ($order->drawerSession?->isClosed()) {
            throw OrderException::drawerSessionClosed();
        }

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
        if ($order->drawerSession?->isClosed()) {
            throw OrderException::drawerSessionClosed();
        }

        $this->drawerSessionService->assertSessionNotUnderReconciliation($order->drawerSession, $by);

        if (!$order->status->canTransitionTo($newStatus)) {
            throw OrderException::invalidTransition($order->status, $newStatus);
        }

        $order->transitionTo($newStatus, $by->id);

        return $order->fresh();
    }

    /**
     * Admin-only operational cleanup transition.
     * Allows ready/delivered fixes after the drawer has been closed,
     * without reopening any financial workflow.
     *
     * @throws OrderException
     */
    public function transitionFromAdmin(Order $order, OrderStatus $newStatus, User $by): Order
    {
        if (!$order->drawerSession?->isClosed()) {
            return $this->transition($order, $newStatus, $by);
        }

        return $this->forceClosedDrawerOperationalTransition($order, $newStatus, $by);
    }

    /**
     * Mark a paid ready order as handed over from the pickup/delivery counter.
     *
     * @throws OrderException
     */
    public function markHandedOver(Order $order, User $by): Order
    {
        if (!$order->isPaid()) {
            throw OrderException::handoverRequiresPaidOrder();
        }

        if ($order->status !== OrderStatus::Ready) {
            throw OrderException::handoverRequiresReadyOrder();
        }

        return $this->transition($order, OrderStatus::Delivered, $by);
    }

    /**
     * Admin-only handover cleanup for orders stuck after drawer close.
     *
     * @throws OrderException
     */
    public function markHandedOverFromAdmin(Order $order, User $by): Order
    {
        if (!$order->isPaid()) {
            throw OrderException::handoverRequiresPaidOrder();
        }

        if ($order->status !== OrderStatus::Ready) {
            throw OrderException::handoverRequiresReadyOrder();
        }

        if (!$order->drawerSession?->isClosed()) {
            return $this->markHandedOver($order, $by);
        }

        return $this->forceClosedDrawerOperationalTransition($order, OrderStatus::Delivered, $by);
    }

    /**
     * Cancel an order.
     *
     * @throws OrderException
     */
    public function cancel(Order $order, User $by, string $reason): Order
    {
        $order->loadMissing(['drawerSession', 'items', 'payments', 'settlement', 'customer']);

        if ($order->drawerSession?->isClosed()) {
            throw OrderException::drawerSessionClosed();
        }

        $this->drawerSessionService->assertSessionNotUnderReconciliation($order->drawerSession, $by);

        if ($order->isCancelled()) {
            throw OrderException::alreadyCancelled();
        }

        if (!$order->isCancellable()) {
            throw OrderException::notCancellable($order->status);
        }

        return DB::transaction(function () use ($order, $by, $reason): Order {
            $reversal = $this->orderReversalService->reverse($order, $by, $reason);
            $hadFinancialSettlement = $order->settledAmount() > 0;

            $order->items()->update([
                'status' => OrderItemStatus::Cancelled,
                'updated_by' => $by->id,
            ]);

            $order->update([
                'status'              => OrderStatus::Cancelled,
                'payment_status'      => $hadFinancialSettlement ? PaymentStatus::Refunded : PaymentStatus::Unpaid,
                'refund_amount'       => $hadFinancialSettlement ? $reversal['total_reversed'] : 0,
                'refund_reason'       => $hadFinancialSettlement ? $reason : null,
                'refunded_by'         => $hadFinancialSettlement ? $by->id : null,
                'refunded_at'         => $hadFinancialSettlement ? now() : null,
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

    /**
     * @throws OrderException
     */
    private function forceClosedDrawerOperationalTransition(Order $order, OrderStatus $newStatus, User $by): Order
    {
        if (!in_array($newStatus, [OrderStatus::Ready, OrderStatus::Delivered], true)) {
            throw OrderException::drawerSessionClosed();
        }

        if (!$order->status->canTransitionTo($newStatus)) {
            throw OrderException::invalidTransition($order->status, $newStatus);
        }

        if ($newStatus === OrderStatus::Delivered && !$order->isPaid()) {
            throw OrderException::handoverRequiresPaidOrder();
        }

        $order->transitionTo($newStatus, $by->id);

        return $order->fresh();
    }
}
