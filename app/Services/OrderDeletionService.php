<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\OrderException;
use App\Models\CashMovement;
use App\Models\MealBenefitLedgerEntry;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderDeletionService
{
    public function __construct(
        private readonly DrawerSessionService $drawerSessionService,
        private readonly RecipeInventoryService $recipeInventoryService,
    ) {}

    public function deleteWithReversal(Order $order, User $by, string $reason): Order
    {
        $order->loadMissing([
            'drawerSession',
            'customer',
            'payments',
            'settlement',
            'items.menuItem.recipeLines.inventoryItem',
        ]);

        if ($order->trashed()) {
            return $order;
        }

        $this->drawerSessionService->assertSessionNotUnderReconciliation($order->drawerSession, $by);

        if ($order->hasNonCashPayments()) {
            throw OrderException::deletionRequiresManualExternalReversal();
        }

        return DB::transaction(function () use ($order, $by, $reason): Order {
            $wasFullyRecordedForCustomer = $order->remainingPayableAmount() <= 0;

            $this->reverseCashCollectionIfNeeded($order, $by, $reason);
            $this->recipeInventoryService->restorePendingForOrder($order, $by->id);
            $this->deleteSettlementArtifacts($order);

            if ($order->customer && $wasFullyRecordedForCustomer) {
                $order->customer->reverseRecordedOrder((float) $order->total);
            }

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

    private function reverseCashCollectionIfNeeded(Order $order, User $by, string $reason): void
    {
        $cashPaid = (float) $order->payments()
            ->where('payment_method', PaymentMethod::Cash->value)
            ->sum('amount');

        if ($cashPaid <= 0) {
            return;
        }

        $existingRefund = CashMovement::query()
            ->where('drawer_session_id', $order->drawer_session_id)
            ->where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->where('type', CashMovementType::Refund->value)
            ->exists();

        if ($existingRefund) {
            return;
        }

        $order->drawerSession->addMovement(
            type: CashMovementType::Refund,
            amount: $cashPaid,
            performedBy: $by->id,
            referenceType: 'order',
            referenceId: $order->id,
            notes: "حذف آمن للطلب {$order->order_number}: {$reason}",
        );
    }

    private function deleteSettlementArtifacts(Order $order): void
    {
        MealBenefitLedgerEntry::query()
            ->where('order_id', $order->id)
            ->delete();

        $order->settlement()?->delete();
    }
}
