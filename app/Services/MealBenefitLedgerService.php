<?php

namespace App\Services;

use App\Enums\MealBenefitLedgerEntryType;
use App\Models\Order;
use App\Models\OrderSettlementLine;
use App\Models\User;
use App\Models\UserMealBenefitProfile;

class MealBenefitLedgerService
{
    public function record(
        User $user,
        MealBenefitLedgerEntryType $entryType,
        float $amount = 0,
        int $mealsCount = 0,
        ?UserMealBenefitProfile $profile = null,
        ?Order $order = null,
        ?OrderSettlementLine $settlementLine = null,
        ?array $period = null,
        ?string $notes = null,
        ?int $actorId = null,
    ) {
        return $user->mealBenefitLedgerEntries()->create([
            'profile_id' => $profile?->id,
            'order_id' => $order?->id,
            'order_settlement_line_id' => $settlementLine?->id,
            'entry_type' => $entryType,
            'amount' => round($amount, 2),
            'meals_count' => $mealsCount,
            'benefit_period_start' => $period['start'] ?? null,
            'benefit_period_end' => $period['end'] ?? null,
            'notes' => $notes,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }
}
