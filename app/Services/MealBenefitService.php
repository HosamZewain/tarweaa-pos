<?php

namespace App\Services;

use App\Enums\MealBenefitLedgerEntryType;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use Illuminate\Support\Carbon;

class MealBenefitService
{
    public function currentPeriodBounds(?Carbon $reference = null): array
    {
        $reference ??= now();

        return [
            'start' => $reference->copy()->startOfMonth()->toDateString(),
            'end' => $reference->copy()->endOfMonth()->toDateString(),
        ];
    }

    public function getProfile(User $user): ?UserMealBenefitProfile
    {
        return $user->mealBenefitProfile()
            ->with('allowedMenuItems:id,name')
            ->first();
    }

    public function getMonthlySummary(User $user, ?Carbon $reference = null): array
    {
        $profile = $this->getProfile($user);
        $bounds = $this->currentPeriodBounds($reference);

        if (!$profile || !$profile->is_active) {
            return [
                'profile' => $profile,
                'period_start' => $bounds['start'],
                'period_end' => $bounds['end'],
                'monthly_allowance_used' => 0.0,
                'monthly_allowance_remaining' => 0.0,
                'free_meal_amount_used' => 0.0,
                'free_meal_amount_remaining' => 0.0,
                'free_meal_count_used' => 0,
                'free_meal_count_remaining' => 0,
            ];
        }

        $entries = $profile->ledgerEntries()
            ->whereDate('benefit_period_start', '>=', $bounds['start'])
            ->whereDate('benefit_period_end', '<=', $bounds['end'])
            ->get();

        $monthlyAllowanceUsed = round((float) $entries
            ->where('entry_type', MealBenefitLedgerEntryType::MonthlyAllowanceUsage)
            ->sum('amount'), 2);

        $freeMealAmountUsed = round((float) $entries
            ->where('entry_type', MealBenefitLedgerEntryType::FreeMealUsage)
            ->sum('amount'), 2);

        $freeMealCountUsed = (int) $entries
            ->where('entry_type', MealBenefitLedgerEntryType::FreeMealUsage)
            ->sum('meals_count');

        return [
            'profile' => $profile,
            'period_start' => $bounds['start'],
            'period_end' => $bounds['end'],
            'monthly_allowance_used' => $monthlyAllowanceUsed,
            'monthly_allowance_remaining' => max(0, round((float) $profile->monthly_allowance_amount - $monthlyAllowanceUsed, 2)),
            'free_meal_amount_used' => $freeMealAmountUsed,
            'free_meal_amount_remaining' => max(0, round((float) ($profile->free_meal_monthly_amount ?? 0) - $freeMealAmountUsed, 2)),
            'free_meal_count_used' => $freeMealCountUsed,
            'free_meal_count_remaining' => max(0, (int) ($profile->free_meal_monthly_count ?? 0) - $freeMealCountUsed),
        ];
    }
}
