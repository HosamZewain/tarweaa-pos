<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderDeletionService
{
    public function __construct(
        private readonly DrawerSessionService $drawerSessionService,
        private readonly OrderReversalService $orderReversalService,
    ) {}

    public function deleteWithReversal(Order $order, User $by, string $reason): Order
    {
        $order->loadMissing(['drawerSession', 'items']);

        if ($order->trashed()) {
            return $order;
        }

        if ($order->drawerSession?->isClosed()) {
            throw OrderException::drawerSessionClosed();
        }

        $this->drawerSessionService->assertSessionNotUnderReconciliation($order->drawerSession, $by);

        if ($order->hasNonCashPayments()) {
            throw OrderException::deletionRequiresManualExternalReversal();
        }

        return DB::transaction(function () use ($order, $by, $reason): Order {
            $this->orderReversalService->reverse($order, $by, $reason);

            $order->items()->update([
                'status' => OrderItemStatus::Cancelled,
                'updated_by' => $by->id,
            ]);

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
                'cancellation_reason' => $reason,
                'updated_by' => $by->id,
            ]);

            $order->delete();

            Log::info('Order deleted with reversal', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'deleted_by' => $by->id,
                'reason' => $reason,
            ]);

            return Order::withTrashed()
                ->with(['customer', 'payments', 'settlement', 'items'])
                ->findOrFail($order->id);
        });
    }
}
