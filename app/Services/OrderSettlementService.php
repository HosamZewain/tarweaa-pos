<?php

namespace App\Services;

use App\Enums\OrderSettlementLineType;
use App\Enums\OrderSettlementType;
use App\Models\OrderSettlement;

class OrderSettlementService
{
    public function syncFromLines(OrderSettlement $settlement): OrderSettlement
    {
        $settlement->loadMissing('order', 'lines');

        $coveredAmount = round((float) $settlement->lines->sum('covered_amount'), 2);
        $commercialTotal = round((float) $settlement->order->total, 2);
        $remainingPayableAmount = max(0, round($commercialTotal - $coveredAmount, 2));

        $uniqueLineTypes = $settlement->lines
            ->pluck('line_type')
            ->filter()
            ->unique(fn ($type) => $type instanceof OrderSettlementLineType ? $type->value : $type);

        $settlementType = match (true) {
            $uniqueLineTypes->count() > 1 => OrderSettlementType::MixedBenefit,
            $uniqueLineTypes->first() === OrderSettlementLineType::OwnerCharge => OrderSettlementType::OwnerCharge,
            $uniqueLineTypes->first() === OrderSettlementLineType::EmployeeMonthlyAllowance => OrderSettlementType::EmployeeAllowance,
            in_array($uniqueLineTypes->first(), [OrderSettlementLineType::EmployeeFreeMealAmount, OrderSettlementLineType::EmployeeFreeMealCount], true) => OrderSettlementType::EmployeeFreeMeal,
            default => OrderSettlementType::Standard,
        };

        $settlement->update([
            'settlement_type' => $settlementType,
            'commercial_total_amount' => $commercialTotal,
            'covered_amount' => $coveredAmount,
            'remaining_payable_amount' => $remainingPayableAmount,
        ]);

        return $settlement->fresh(['order', 'lines']);
    }
}
