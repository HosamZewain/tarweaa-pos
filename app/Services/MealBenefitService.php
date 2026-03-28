<?php

namespace App\Services;

use App\Enums\MealBenefitLedgerEntryType;
use App\Enums\OrderSettlementLineType;
use App\Enums\UserMealBenefitFreeMealType;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    public function getActiveProfileOrFail(User $user): UserMealBenefitProfile
    {
        $profile = $this->getProfile($user);

        if (!$profile || !$profile->is_active) {
            throw OrderException::mealBenefitProfileRequired();
        }

        return $profile;
    }

    public function assertCanReceiveOwnerCharge(User $user): UserMealBenefitProfile
    {
        $profile = $this->getActiveProfileOrFail($user);

        if (
            !$profile->can_receive_owner_charge_orders
            || !$user->hasRole(['owner', 'admin', 'manager'])
        ) {
            throw OrderException::invalidOwnerChargeAccount();
        }

        return $profile;
    }

    public function getEligibleOrderItems(Order $order, UserMealBenefitProfile $profile): Collection
    {
        $allowedIds = $profile->allowedMenuItems->pluck('id')->all();

        $order->loadMissing('activeItems.menuItem');

        return $order->activeItems
            ->filter(fn (OrderItem $item) => in_array($item->menu_item_id, $allowedIds, true))
            ->values();
    }

    public function getEligibleOrderItemBreakdown(Order $order, UserMealBenefitProfile $profile): Collection
    {
        $allowedIds = $profile->allowedMenuItems->pluck('id')->all();

        $order->loadMissing('activeItems.menuItem');

        $lines = $order->activeItems->map(fn (OrderItem $item) => [
            'order_item' => $item,
            'menu_item_id' => $item->menu_item_id,
            'quantity' => (int) $item->quantity,
            'gross_total' => round((float) $item->total, 2),
        ]);

        return $this->allocateOrderDiscountAcrossLines($lines, (float) $order->discount_amount)
            ->filter(fn (array $line) => in_array($line['menu_item_id'], $allowedIds, true))
            ->map(fn (array $line) => [
                'order_item' => $line['order_item'],
                'menu_item_id' => $line['menu_item_id'],
                'quantity' => $line['quantity'],
                'gross_total' => $line['gross_total'],
                'eligible_total' => $line['net_total'],
            ])
            ->values();
    }

    public function getEligibleItemsTotal(Order $order, UserMealBenefitProfile $profile): float
    {
        return round((float) $this->getEligibleOrderItemBreakdown($order, $profile)->sum('eligible_total'), 2);
    }

    public function calculateDiscountAmount(float $subtotal, ?string $discountType, float $discountValue): float
    {
        if ($discountValue <= 0) {
            return 0.0;
        }

        $discountAmount = $discountType === 'percentage'
            ? $subtotal * ($discountValue / 100)
            : $discountValue;

        return round($discountAmount, 2);
    }

    public function allocateOrderDiscountAcrossLines(Collection $lines, float $discountAmount): Collection
    {
        $subtotal = round((float) $lines->sum('gross_total'), 2);

        if ($subtotal <= 0 || $discountAmount <= 0 || $lines->isEmpty()) {
            return $lines->values()->map(function ($line) {
                $line['discount_share'] = 0.0;
                $line['net_total'] = round((float) ($line['gross_total'] ?? 0), 2);

                return $line;
            });
        }

        $totalDiscount = round($discountAmount, 2);
        $remainingDiscount = $totalDiscount;
        $lastIndex = $lines->count() - 1;

        return $lines->values()->map(function ($line, int $index) use ($subtotal, $totalDiscount, $lastIndex, &$remainingDiscount) {
            $grossTotal = round((float) ($line['gross_total'] ?? 0), 2);

            if ($index === $lastIndex) {
                $discountShare = $remainingDiscount;
            } else {
                $discountShare = round(((float) $grossTotal / $subtotal) * $totalDiscount, 2);
                $discountShare = min($discountShare, $remainingDiscount);
            }

            $remainingDiscount = round($remainingDiscount - $discountShare, 2);
            $line['discount_share'] = $discountShare;
            $line['net_total'] = max(0, round($grossTotal - $discountShare, 2));

            return $line;
        });
    }

    public function calculateFreeMealCoverage(
        Collection $eligibleLines,
        UserMealBenefitProfile $profile,
        array $summary,
    ): Collection {
        $remainingCount = (int) ($summary['free_meal_count_remaining'] ?? 0);
        $remainingAmount = (float) ($summary['free_meal_amount_remaining'] ?? 0);

        return $eligibleLines->map(function (array $line) use ($profile, &$remainingCount, &$remainingAmount) {
            $eligibleAmount = round((float) ($line['eligible_total'] ?? 0), 2);
            $quantity = max(0, (int) ($line['quantity'] ?? 0));
            $coveredAmount = 0.0;
            $coveredQuantity = null;
            $mealsCount = 0;
            $lineType = $profile->free_meal_type === UserMealBenefitFreeMealType::Count
                ? OrderSettlementLineType::EmployeeFreeMealCount
                : OrderSettlementLineType::EmployeeFreeMealAmount;

            if ($profile->free_meal_type === UserMealBenefitFreeMealType::Count) {
                $coveredQuantity = min($remainingCount, $quantity);

                if ($coveredQuantity > 0 && $quantity > 0) {
                    $unitAmount = round($eligibleAmount / $quantity, 2);
                    $coveredAmount = round($unitAmount * $coveredQuantity, 2);
                    $remainingCount -= $coveredQuantity;
                    $mealsCount = $coveredQuantity;
                }
            } else {
                $coveredAmount = min($remainingAmount, $eligibleAmount);
                $remainingAmount = max(0, round($remainingAmount - $coveredAmount, 2));
            }

            return array_merge($line, [
                'line_type' => $lineType,
                'covered_amount' => round($coveredAmount, 2),
                'covered_quantity' => $coveredQuantity,
                'meals_count' => $mealsCount,
            ]);
        })->values();
    }

    public function eligibleUsersForScenario(string $scenario, ?string $search = null, int $limit = 30): Collection
    {
        $query = User::query()
            ->where('is_active', true)
            ->with('roles:id,name,display_name');

        match ($scenario) {
            'owner_charge' => $query
                ->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['owner', 'admin', 'manager']))
                ->whereHas('mealBenefitProfile', fn ($profileQuery) => $profileQuery
                    ->where('is_active', true)
                    ->where('can_receive_owner_charge_orders', true)),
            'employee_allowance' => $query
                ->whereHas('mealBenefitProfile', fn ($profileQuery) => $profileQuery
                    ->where('is_active', true)
                    ->where('monthly_allowance_enabled', true)),
            'employee_free_meal' => $query
                ->whereHas('mealBenefitProfile', fn ($profileQuery) => $profileQuery
                    ->where('is_active', true)
                    ->where('free_meal_enabled', true)),
            default => throw new \InvalidArgumentException('Unsupported settlement scenario.'),
        };

        if ($search) {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'phone' => $user->phone,
                'roles' => $user->roles->map(fn ($role) => [
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                ])->values(),
            ])
            ->values();
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
