<?php

namespace App\Services;

use App\Enums\OrderSettlementType;
use App\Enums\UserMealBenefitFreeMealType;
use App\Exceptions\OrderException;
use App\Models\MenuItem;
use App\Models\MenuItemModifier;
use App\Models\User;
use Illuminate\Support\Collection;

class PosSettlementPreviewService
{
    public function __construct(
        private readonly MealBenefitService $mealBenefitService,
    ) {}

    public function listCandidates(string $scenario, ?string $search = null, int $limit = 30): Collection
    {
        return $this->mealBenefitService->eligibleUsersForScenario($scenario, $search, $limit);
    }

    public function preview(
        string $scenario,
        array $items,
        ?User $beneficiary = null,
        ?User $chargeAccount = null,
        ?string $discountType = null,
        float $discountValue = 0.0,
        float $taxRate = 0.0,
        float $deliveryFee = 0.0,
    ): array {
        $cart = $this->buildCartContext($items, $discountType, $discountValue, $taxRate, $deliveryFee);

        return match ($scenario) {
            'owner_charge' => $this->previewOwnerCharge($cart, $chargeAccount),
            'employee_allowance' => $this->previewEmployeeAllowance($cart, $beneficiary),
            'employee_free_meal' => $this->previewEmployeeFreeMeal($cart, $beneficiary),
            default => throw new \InvalidArgumentException('Unsupported settlement scenario.'),
        };
    }

    private function previewOwnerCharge(array $cart, ?User $chargeAccount): array
    {
        if (!$chargeAccount) {
            throw OrderException::invalidOwnerChargeAccount();
        }

        $profile = $this->mealBenefitService->assertCanReceiveOwnerCharge($chargeAccount);

        return array_merge($this->basePreviewPayload($cart), [
            'settlement_type' => OrderSettlementType::OwnerCharge->value,
            'settlement_type_label' => OrderSettlementType::OwnerCharge->label(),
            'beneficiary_user' => null,
            'charge_account_user' => [
                'id' => $chargeAccount->id,
                'name' => $chargeAccount->name,
            ],
            'profile_id' => $profile->id,
            'covered_amount' => $cart['commercial_total_amount'],
            'remaining_payable_amount' => 0.0,
            'can_apply' => true,
            'message' => 'سيتم تحميل كامل قيمة الطلب على الحساب المحدد بدون دفع فوري.',
        ]);
    }

    private function previewEmployeeAllowance(array $cart, ?User $employee): array
    {
        if (!$employee) {
            throw OrderException::mealBenefitProfileRequired();
        }

        $profile = $this->mealBenefitService->getActiveProfileOrFail($employee);

        if (!$profile->monthly_allowance_enabled) {
            throw OrderException::monthlyAllowanceNotEnabled();
        }

        $summary = $this->mealBenefitService->getMonthlySummary($employee);
        $coveredAmount = min($cart['commercial_total_amount'], (float) $summary['monthly_allowance_remaining']);

        return array_merge($this->basePreviewPayload($cart), [
            'settlement_type' => OrderSettlementType::EmployeeAllowance->value,
            'settlement_type_label' => OrderSettlementType::EmployeeAllowance->label(),
            'beneficiary_user' => [
                'id' => $employee->id,
                'name' => $employee->name,
            ],
            'charge_account_user' => null,
            'profile_id' => $profile->id,
            'covered_amount' => round($coveredAmount, 2),
            'remaining_payable_amount' => max(0, round($cart['commercial_total_amount'] - $coveredAmount, 2)),
            'monthly_allowance_used' => (float) $summary['monthly_allowance_used'],
            'monthly_allowance_remaining' => (float) $summary['monthly_allowance_remaining'],
            'can_apply' => true,
            'message' => $coveredAmount > 0
                ? 'سيتم استخدام الرصيد الشهري المتبقي أولاً، ويمكن تحصيل الفرق إذا وجد.'
                : 'لا يوجد رصيد بدل شهري متبقٍ لهذا الشهر. سيتم تحصيل كامل الطلب بالطريقة العادية.',
        ]);
    }

    private function previewEmployeeFreeMeal(array $cart, ?User $employee): array
    {
        if (!$employee) {
            throw OrderException::mealBenefitProfileRequired();
        }

        $profile = $this->mealBenefitService->getActiveProfileOrFail($employee);

        if (!$profile->free_meal_enabled) {
            throw OrderException::freeMealBenefitNotEnabled();
        }

        $summary = $this->mealBenefitService->getMonthlySummary($employee);
        $allowedIds = $profile->allowedMenuItems->pluck('id')->all();
        $eligibleLines = collect($cart['lines'])
            ->filter(fn (array $line) => in_array($line['menu_item_id'], $allowedIds, true))
            ->map(fn (array $line) => array_merge($line, [
                'eligible_total' => $line['net_total'],
            ]))
            ->values();

        if ($eligibleLines->isEmpty()) {
            return array_merge($this->basePreviewPayload($cart), [
                'settlement_type' => OrderSettlementType::EmployeeFreeMeal->value,
                'settlement_type_label' => OrderSettlementType::EmployeeFreeMeal->label(),
                'beneficiary_user' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                ],
                'charge_account_user' => null,
                'profile_id' => $profile->id,
                'eligible_items_total' => 0.0,
                'covered_amount' => 0.0,
                'remaining_payable_amount' => $cart['commercial_total_amount'],
                'free_meal_type' => $profile->free_meal_type?->value,
                'free_meal_type_label' => $profile->free_meal_type?->label(),
                'free_meal_amount_used' => (float) $summary['free_meal_amount_used'],
                'free_meal_amount_remaining' => (float) $summary['free_meal_amount_remaining'],
                'free_meal_count_used' => (int) $summary['free_meal_count_used'],
                'free_meal_count_remaining' => (int) $summary['free_meal_count_remaining'],
                'can_apply' => false,
                'message' => 'لا توجد أصناف مؤهلة في هذا الطلب لميزة الوجبة المجانية.',
            ]);
        }

        $coverage = $this->mealBenefitService->calculateFreeMealCoverage($eligibleLines, $profile, $summary);
        $coveredAmount = round((float) $coverage->sum('covered_amount'), 2);

        return array_merge($this->basePreviewPayload($cart), [
            'settlement_type' => OrderSettlementType::EmployeeFreeMeal->value,
            'settlement_type_label' => OrderSettlementType::EmployeeFreeMeal->label(),
            'beneficiary_user' => [
                'id' => $employee->id,
                'name' => $employee->name,
            ],
            'charge_account_user' => null,
            'profile_id' => $profile->id,
            'eligible_items_total' => round((float) $eligibleLines->sum('net_total'), 2),
            'covered_amount' => $coveredAmount,
            'remaining_payable_amount' => max(0, round($cart['commercial_total_amount'] - $coveredAmount, 2)),
            'free_meal_type' => $profile->free_meal_type?->value,
            'free_meal_type_label' => $profile->free_meal_type?->label(),
            'free_meal_amount_used' => (float) $summary['free_meal_amount_used'],
            'free_meal_amount_remaining' => (float) $summary['free_meal_amount_remaining'],
            'free_meal_count_used' => (int) $summary['free_meal_count_used'],
            'free_meal_count_remaining' => (int) $summary['free_meal_count_remaining'],
            'covered_meals_count' => (int) $coverage->sum('meals_count'),
            'can_apply' => true,
            'message' => $profile->free_meal_type === UserMealBenefitFreeMealType::Count
                ? 'تم حساب التغطية بناءً على عدد الوجبات المتبقي للأصناف المؤهلة فقط.'
                : 'تم حساب التغطية بناءً على المبلغ المتبقي للأصناف المؤهلة فقط.',
        ]);
    }

    private function buildCartContext(
        array $items,
        ?string $discountType,
        float $discountValue,
        float $taxRate,
        float $deliveryFee,
    ): array {
        $preparedLines = $this->prepareLines($items);
        $subtotal = round((float) $preparedLines->sum('gross_total'), 2);
        $discountAmount = $this->mealBenefitService->calculateDiscountAmount($subtotal, $discountType, $discountValue);
        $discountedLines = $this->mealBenefitService->allocateOrderDiscountAcrossLines($preparedLines, $discountAmount);
        $afterDiscount = round($subtotal - $discountAmount, 2);
        $taxAmount = round($afterDiscount * ($taxRate / 100), 2);
        $commercialTotal = round($afterDiscount + $taxAmount + $deliveryFee, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'delivery_fee' => round($deliveryFee, 2),
            'commercial_total_amount' => $commercialTotal,
            'lines' => $discountedLines->values()->all(),
        ];
    }

    private function prepareLines(array $items): Collection
    {
        $itemIds = collect($items)->pluck('menu_item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $modifierIds = collect($items)
            ->flatMap(fn (array $item) => array_keys($item['modifiers'] ?? []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $menuItems = MenuItem::query()
            ->with(['variants' => fn ($query) => $query->where('is_available', true)])
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $modifiers = MenuItemModifier::query()
            ->whereIn('id', $modifierIds)
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        return collect($items)->map(function (array $item) use ($menuItems, $modifiers) {
            $menuItem = $menuItems->get((int) $item['menu_item_id']);

            if (!$menuItem) {
                throw new \InvalidArgumentException('Selected menu item is not available.');
            }

            $variant = null;
            if (!empty($item['variant_id'])) {
                $variant = $menuItem->variants->firstWhere('id', (int) $item['variant_id']);
            } elseif ($menuItem->isVariable() && $menuItem->variants->isNotEmpty()) {
                $variant = $menuItem->variants->first();
            }

            $unitPrice = (float) ($variant?->price ?? $menuItem->base_price);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $modifierTotal = collect($item['modifiers'] ?? [])->sum(function ($qty, $modifierId) use ($modifiers) {
                $modifier = $modifiers->get((int) $modifierId);

                return $modifier ? ((float) $modifier->price * max(1, (int) $qty)) : 0;
            });

            $grossTotal = round(($unitPrice * $quantity) + $modifierTotal - (float) ($item['discount_amount'] ?? 0), 2);

            return [
                'menu_item_id' => $menuItem->id,
                'item_name' => $menuItem->name,
                'quantity' => $quantity,
                'gross_total' => max(0, $grossTotal),
            ];
        })->values();
    }

    private function basePreviewPayload(array $cart): array
    {
        return [
            'subtotal' => $cart['subtotal'],
            'discount_amount' => $cart['discount_amount'],
            'tax_amount' => $cart['tax_amount'],
            'delivery_fee' => $cart['delivery_fee'],
            'commercial_total_amount' => $cart['commercial_total_amount'],
            'eligible_items_total' => $cart['commercial_total_amount'],
        ];
    }
}
