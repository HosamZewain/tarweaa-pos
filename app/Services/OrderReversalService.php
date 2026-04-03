<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\MealBenefitLedgerEntry;
use App\Models\Order;
use App\Models\User;

class OrderReversalService
{
    public function __construct(
        private readonly RecipeInventoryService $recipeInventoryService,
    ) {}

    /**
     * Reverse all internal effects of an order while keeping the order row itself intact.
     *
     * @return array{cash_refunded: float, covered_reversed: float, total_reversed: float}
     */
    public function reverse(Order $order, User $by, string $reason): array
    {
        $order->loadMissing([
            'drawerSession',
            'customer',
            'payments',
            'settlement',
            'items.menuItem.recipeLines.inventoryItem',
        ]);

        if ($order->hasNonCashPayments()) {
            throw OrderException::cancellationRequiresManualExternalReversal();
        }

        // Cancel / safe delete are treated as internal voids. The order will drop
        // out of sales and drawer cash totals, so adding a refund cash movement
        // here would subtract the same cash effect a second time.
        $cashRefunded = $this->reversedCashAmount($order);
        $coveredReversed = round($order->coveredAmount(), 2);

        $this->recipeInventoryService->restorePendingForOrder($order, $by->id);
        $this->deleteSettlementArtifacts($order);

        if ($order->customer && $order->remainingPayableAmount() <= 0) {
            $order->customer->reverseRecordedOrder((float) $order->total);
        }

        return [
            'cash_refunded' => $cashRefunded,
            'covered_reversed' => $coveredReversed,
            'total_reversed' => round($cashRefunded + $coveredReversed, 2),
        ];
    }

    private function reversedCashAmount(Order $order): float
    {
        return round((float) $order->payments()
            ->where('payment_method', \App\Enums\PaymentMethod::Cash->value)
            ->sum('amount'), 2);
    }

    private function deleteSettlementArtifacts(Order $order): void
    {
        MealBenefitLedgerEntry::query()
            ->where('order_id', $order->id)
            ->delete();

        $order->settlement()?->delete();
    }
}
