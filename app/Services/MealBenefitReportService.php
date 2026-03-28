<?php

namespace App\Services;

use App\Enums\MealBenefitLedgerEntryType;
use App\Enums\OrderSettlementLineType;
use App\Enums\OrderSettlementType;
use App\Enums\UserMealBenefitFreeMealType;
use App\Models\MealBenefitLedgerEntry;
use App\Models\OrderSettlement;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MealBenefitReportService
{
    public function __construct(
        private readonly MealBenefitService $mealBenefitService,
    ) {}

    public function buildMonthlyReport(?Carbon $reference = null, ?int $userId = null, ?string $entryType = null): array
    {
        $reference ??= today();
        $period = $this->createdAtPeriodBounds($reference);
        $selectedUser = $userId ? User::query()->find($userId) : null;
        $selectedUserSummary = $selectedUser
            ? $this->mealBenefitService->getMonthlySummary($selectedUser, $reference)
            : null;

        $entries = $this->baseLedgerEntriesQuery($period, $userId, $entryType)->get();

        return [
            'period_label' => $reference->translatedFormat('F Y'),
            'selected_user' => $selectedUser ? [
                'id' => $selectedUser->id,
                'name' => $selectedUser->name,
                'username' => $selectedUser->username,
            ] : null,
            'selected_user_summary' => $selectedUserSummary ? [
                'monthly_allowance_used' => (float) $selectedUserSummary['monthly_allowance_used'],
                'monthly_allowance_remaining' => (float) $selectedUserSummary['monthly_allowance_remaining'],
                'free_meal_amount_used' => (float) $selectedUserSummary['free_meal_amount_used'],
                'free_meal_amount_remaining' => (float) $selectedUserSummary['free_meal_amount_remaining'],
                'free_meal_count_used' => (int) $selectedUserSummary['free_meal_count_used'],
                'free_meal_count_remaining' => (int) $selectedUserSummary['free_meal_count_remaining'],
                'profile_mode' => $selectedUserSummary['profile']?->benefitModeLabel() ?? 'بدون ملف نشط',
                'is_active' => (bool) ($selectedUserSummary['profile']?->is_active ?? false),
            ] : null,
            'summary_cards' => [
                'entries_count' => $entries->count(),
                'owner_charge_amount' => round((float) $entries->where('entry_type', MealBenefitLedgerEntryType::OwnerChargeUsage)->sum('amount'), 2),
                'allowance_amount' => round((float) $entries->where('entry_type', MealBenefitLedgerEntryType::MonthlyAllowanceUsage)->sum('amount'), 2),
                'free_meal_amount' => round((float) $entries->where('entry_type', MealBenefitLedgerEntryType::FreeMealUsage)->sum('amount'), 2),
                'free_meal_count' => (int) $entries->where('entry_type', MealBenefitLedgerEntryType::FreeMealUsage)->sum('meals_count'),
                'supplemental_payment_amount' => round((float) $entries->where('entry_type', MealBenefitLedgerEntryType::SupplementalPayment)->sum('amount'), 2),
            ],
            'owner_charge_statement' => $this->getOwnerChargeStatement($reference, $userId),
            'allowance_report' => $this->getAllowanceUsageReport($reference, $userId),
            'free_meal_report' => $this->getFreeMealUsageReport($reference, $userId),
            'mixed_coverage_report' => $this->getMixedCoverageReport($reference, $userId),
            'entries' => $entries->map(fn (MealBenefitLedgerEntry $entry) => [
                'id' => $entry->id,
                'user_name' => $entry->user?->name ?? '—',
                'entry_type' => $entry->entry_type?->label() ?? '—',
                'amount' => round((float) $entry->amount, 2),
                'meals_count' => (int) $entry->meals_count,
                'order_number' => $entry->order?->order_number,
                'order_url' => $entry->order ? "/admin/orders/{$entry->order->id}" : null,
                'menu_item_name' => $entry->settlementLine?->menuItem?->name,
                'covered_quantity' => $entry->settlementLine?->covered_quantity,
                'notes' => $entry->notes,
                'created_at' => $entry->created_at?->translatedFormat('Y/m/d h:i A'),
            ])->all(),
        ];
    }

    public function getOwnerChargeStatement(?Carbon $reference = null, ?int $userId = null): array
    {
        $entries = $this->baseLedgerEntriesQuery(
            $this->createdAtPeriodBounds($reference ?? today()),
            $userId,
            MealBenefitLedgerEntryType::OwnerChargeUsage->value,
        )->get();

        return [
            'total_amount' => round((float) $entries->sum('amount'), 2),
            'orders_count' => $entries->pluck('order_id')->filter()->unique()->count(),
            'rows' => $entries->map(fn (MealBenefitLedgerEntry $entry) => [
                'user_name' => $entry->user?->name ?? '—',
                'date' => $entry->created_at?->translatedFormat('Y/m/d h:i A'),
                'order_number' => $entry->order?->order_number,
                'order_url' => $entry->order ? "/admin/orders/{$entry->order->id}" : null,
                'charged_amount' => round((float) $entry->amount, 2),
                'notes' => $entry->notes,
            ])->all(),
        ];
    }

    public function getAllowanceUsageReport(?Carbon $reference = null, ?int $userId = null): array
    {
        $reference ??= today();
        $period = $this->benefitPeriodBounds($reference);
        $profiles = UserMealBenefitProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->where('monthly_allowance_enabled', true)
            ->when($userId, fn (Builder $query, int $selectedUserId) => $query->where('user_id', $selectedUserId))
            ->orderBy('user_id')
            ->get();

        $rows = $profiles->map(function (UserMealBenefitProfile $profile) use ($reference, $period) {
            $summary = $this->mealBenefitService->getMonthlySummary($profile->user, $reference);
            $usageEntries = $profile->ledgerEntries()
                ->where('entry_type', MealBenefitLedgerEntryType::MonthlyAllowanceUsage)
                ->whereDate('benefit_period_start', '>=', $period['start'])
                ->whereDate('benefit_period_end', '<=', $period['end'])
                ->get();

            $differenceEntries = $profile->user->mealBenefitLedgerEntries()
                ->where('entry_type', MealBenefitLedgerEntryType::SupplementalPayment)
                ->whereBetween('created_at', [$period['start']->startOfDay(), $period['end']->endOfDay()])
                ->whereHas('order.settlement.lines', fn (Builder $query) => $query->where('line_type', OrderSettlementLineType::EmployeeMonthlyAllowance))
                ->get();

            return [
                'user_name' => $profile->user?->name ?? '—',
                'configured_monthly_allowance' => round((float) $profile->monthly_allowance_amount, 2),
                'consumed_amount' => round((float) $summary['monthly_allowance_used'], 2),
                'remaining_amount' => round((float) $summary['monthly_allowance_remaining'], 2),
                'covered_orders_count' => $usageEntries->pluck('order_id')->filter()->unique()->count(),
                'paid_differences_amount' => round((float) $differenceEntries->sum('amount'), 2),
            ];
        })->values();

        return [
            'rows' => $rows->all(),
            'totals' => [
                'profiles_count' => $rows->count(),
                'consumed_amount' => round((float) $rows->sum('consumed_amount'), 2),
                'remaining_amount' => round((float) $rows->sum('remaining_amount'), 2),
                'paid_differences_amount' => round((float) $rows->sum('paid_differences_amount'), 2),
            ],
        ];
    }

    public function getFreeMealUsageReport(?Carbon $reference = null, ?int $userId = null): array
    {
        $reference ??= today();
        $period = $this->benefitPeriodBounds($reference);
        $profiles = UserMealBenefitProfile::query()
            ->with('user')
            ->where('is_active', true)
            ->where('free_meal_enabled', true)
            ->when($userId, fn (Builder $query, int $selectedUserId) => $query->where('user_id', $selectedUserId))
            ->orderBy('user_id')
            ->get();

        $rows = $profiles->map(function (UserMealBenefitProfile $profile) use ($reference, $period) {
            $summary = $this->mealBenefitService->getMonthlySummary($profile->user, $reference);
            $usageEntries = $profile->ledgerEntries()
                ->where('entry_type', MealBenefitLedgerEntryType::FreeMealUsage)
                ->whereDate('benefit_period_start', '>=', $period['start'])
                ->whereDate('benefit_period_end', '<=', $period['end'])
                ->get();

            return [
                'user_name' => $profile->user?->name ?? '—',
                'benefit_type' => $profile->free_meal_type?->label() ?? '—',
                'configured_limit' => $profile->free_meal_type === UserMealBenefitFreeMealType::Count
                    ? number_format((int) ($profile->free_meal_monthly_count ?? 0)) . ' وجبة'
                    : number_format((float) ($profile->free_meal_monthly_amount ?? 0), 2) . ' ج.م',
                'consumed_amount' => round((float) $summary['free_meal_amount_used'], 2),
                'remaining_amount' => round((float) $summary['free_meal_amount_remaining'], 2),
                'consumed_count' => (int) $summary['free_meal_count_used'],
                'remaining_count' => (int) $summary['free_meal_count_remaining'],
                'covered_orders_count' => $usageEntries->pluck('order_id')->filter()->unique()->count(),
            ];
        })->values();

        return [
            'rows' => $rows->all(),
            'totals' => [
                'profiles_count' => $rows->count(),
                'consumed_amount' => round((float) $rows->sum('consumed_amount'), 2),
                'covered_orders_count' => (int) $rows->sum('covered_orders_count'),
                'consumed_count' => (int) $rows->sum('consumed_count'),
            ],
        ];
    }

    public function getMixedCoverageReport(?Carbon $reference = null, ?int $userId = null): array
    {
        $reference ??= today();
        $period = $this->createdAtPeriodBounds($reference);

        $rows = OrderSettlement::query()
            ->with(['beneficiaryUser:id,name', 'order:id,order_number,total,payment_status'])
            ->whereBetween('created_at', [$period['start'], $period['end']])
            ->where('covered_amount', '>', 0)
            ->where('remaining_payable_amount', '>', 0)
            ->whereIn('settlement_type', [
                OrderSettlementType::EmployeeAllowance,
                OrderSettlementType::EmployeeFreeMeal,
                OrderSettlementType::MixedBenefit,
            ])
            ->when($userId, fn (Builder $query, int $selectedUserId) => $query->where('beneficiary_user_id', $selectedUserId))
            ->orderByDesc('created_at')
            ->get()
            ->map(function (OrderSettlement $settlement) use ($period) {
                $supplementalPayments = MealBenefitLedgerEntry::query()
                    ->where('entry_type', MealBenefitLedgerEntryType::SupplementalPayment)
                    ->where('order_id', $settlement->order_id)
                    ->whereBetween('created_at', [$period['start'], $period['end']])
                    ->sum('amount');

                return [
                    'user_name' => $settlement->beneficiaryUser?->name ?? '—',
                    'order_number' => $settlement->order?->order_number,
                    'order_url' => $settlement->order ? "/admin/orders/{$settlement->order->id}" : null,
                    'order_total' => round((float) $settlement->commercial_total_amount, 2),
                    'covered_amount' => round((float) $settlement->covered_amount, 2),
                    'paid_difference' => round((float) ($supplementalPayments > 0 ? $supplementalPayments : $settlement->remaining_payable_amount), 2),
                    'date' => $settlement->created_at?->translatedFormat('Y/m/d h:i A'),
                    'settlement_type' => $settlement->settlement_type?->label() ?? '—',
                ];
            })
            ->values();

        return [
            'rows' => $rows->all(),
            'totals' => [
                'orders_count' => $rows->count(),
                'covered_amount' => round((float) $rows->sum('covered_amount'), 2),
                'paid_differences_amount' => round((float) $rows->sum('paid_difference'), 2),
            ],
        ];
    }

    private function baseLedgerEntriesQuery(array $period, ?int $userId = null, ?string $entryType = null): Builder
    {
        return MealBenefitLedgerEntry::query()
            ->with([
                'user:id,name,username',
                'profile:id,user_id,is_active,can_receive_owner_charge_orders,monthly_allowance_enabled,monthly_allowance_amount,free_meal_enabled,free_meal_type,free_meal_monthly_count,free_meal_monthly_amount',
                'order:id,order_number,total',
                'settlementLine:id,order_settlement_id,order_item_id,menu_item_id,covered_quantity',
                'settlementLine.menuItem:id,name',
            ])
            ->whereBetween('created_at', [$period['start'], $period['end']])
            ->when($entryType, fn (Builder $query, string $type) => $query->where('entry_type', $type))
            ->when($userId, fn (Builder $query, int $selectedUserId) => $query->where('user_id', $selectedUserId))
            ->orderByDesc('created_at');
    }

    private function createdAtPeriodBounds(Carbon $reference): array
    {
        return [
            'start' => $reference->copy()->startOfMonth()->startOfDay(),
            'end' => $reference->copy()->endOfMonth()->endOfDay(),
        ];
    }

    private function benefitPeriodBounds(Carbon $reference): array
    {
        return [
            'start' => $reference->copy()->startOfMonth(),
            'end' => $reference->copy()->endOfMonth(),
        ];
    }
}
