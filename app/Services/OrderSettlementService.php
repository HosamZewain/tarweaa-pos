<?php

namespace App\Services;

use App\Enums\MealBenefitLedgerEntryType;
use App\Enums\OrderSettlementLineType;
use App\Enums\OrderSettlementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\MealBenefitLedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSettlement;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderSettlementService
{
    public function __construct(
        private readonly DrawerSessionService $drawerSessionService,
        private readonly RecipeInventoryService $recipeInventoryService,
        private readonly MealBenefitService $mealBenefitService,
        private readonly MealBenefitLedgerService $mealBenefitLedgerService,
    ) {}

    public function applyOwnerCharge(Order $order, User $chargeAccount, int $actorId, ?string $notes = null): Order
    {
        $profile = $this->mealBenefitService->assertCanReceiveOwnerCharge($chargeAccount);

        return DB::transaction(function () use ($order, $chargeAccount, $profile, $actorId, $notes): Order {
            $order = $this->prepareOrderForSettlement($order, $actorId);
            $settlement = $this->freshSettlement($order, $actorId, [
                'charge_account_user_id' => $chargeAccount->id,
                'notes' => $notes,
            ]);

            $line = $settlement->lines()->create([
                'order_id' => $order->id,
                'line_type' => OrderSettlementLineType::OwnerCharge,
                'user_id' => $chargeAccount->id,
                'profile_id' => $profile->id,
                'eligible_amount' => (float) $order->total,
                'covered_amount' => (float) $order->total,
                'notes' => $notes,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->mealBenefitLedgerService->record(
                user: $chargeAccount,
                entryType: MealBenefitLedgerEntryType::OwnerChargeUsage,
                amount: (float) $order->total,
                profile: $profile,
                order: $order,
                settlementLine: $line,
                notes: $notes ?: "تحميل الطلب {$order->order_number} على الحساب",
                actorId: $actorId,
            );

            $this->syncFromLines($settlement);

            return $this->syncOrderFinancialState($order->fresh(['settlement.lines']), $actorId);
        });
    }

    public function applyEmployeeMonthlyAllowance(Order $order, User $employee, int $actorId, ?string $notes = null): Order
    {
        $profile = $this->mealBenefitService->getActiveProfileOrFail($employee);

        if (!$profile->monthly_allowance_enabled) {
            throw OrderException::monthlyAllowanceNotEnabled();
        }

        return DB::transaction(function () use ($order, $employee, $profile, $actorId, $notes): Order {
            $order = $this->prepareOrderForSettlement($order, $actorId);
            $settlement = $this->freshSettlement($order, $actorId, [
                'beneficiary_user_id' => $employee->id,
                'notes' => $notes,
            ]);

            $summary = $this->mealBenefitService->getBenefitSummary($employee);
            $period = [
                'start' => $summary['period_start'],
                'end' => $summary['period_end'],
            ];
            $eligibleAmount = round((float) $order->total, 2);
            $coveredAmount = min($eligibleAmount, (float) $summary['monthly_allowance_remaining']);

            $line = $settlement->lines()->create([
                'order_id' => $order->id,
                'line_type' => OrderSettlementLineType::EmployeeMonthlyAllowance,
                'user_id' => $employee->id,
                'profile_id' => $profile->id,
                'eligible_amount' => $eligibleAmount,
                'covered_amount' => $coveredAmount,
                'benefit_period_start' => $period['start'],
                'benefit_period_end' => $period['end'],
                'notes' => $notes,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            if ($coveredAmount > 0) {
                $this->mealBenefitLedgerService->record(
                    user: $employee,
                    entryType: MealBenefitLedgerEntryType::MonthlyAllowanceUsage,
                    amount: $coveredAmount,
                    profile: $profile,
                    order: $order,
                    settlementLine: $line,
                    period: $period,
                    notes: $notes ?: "استخدام بدل الموظف ({$summary['period_type_label']}) في الطلب {$order->order_number}",
                    actorId: $actorId,
                );
            }

            $this->syncFromLines($settlement);

            return $this->syncOrderFinancialState($order->fresh(['settlement.lines']), $actorId);
        });
    }

    public function applyEmployeeFreeMealBenefit(Order $order, User $employee, int $actorId, ?string $notes = null): Order
    {
        $profile = $this->mealBenefitService->getActiveProfileOrFail($employee);

        if (!$profile->free_meal_enabled) {
            throw OrderException::freeMealBenefitNotEnabled();
        }

        return DB::transaction(function () use ($order, $employee, $profile, $actorId, $notes): Order {
            $order = $this->prepareOrderForSettlement($order, $actorId);
            $settlement = $this->freshSettlement($order, $actorId, [
                'beneficiary_user_id' => $employee->id,
                'notes' => $notes,
            ]);

            $summary = $this->mealBenefitService->getBenefitSummary($employee);
            $period = [
                'start' => $summary['period_start'],
                'end' => $summary['period_end'],
            ];
            $eligibleItems = $this->mealBenefitService->getEligibleOrderItemBreakdown($order, $profile);

            if ($eligibleItems->isEmpty()) {
                throw OrderException::noEligibleBenefitItems();
            }

            $this->createFreeMealSettlementLines(
                settlement: $settlement,
                employee: $employee,
                profile: $profile,
                eligibleItems: $eligibleItems,
                summary: $summary,
                period: $period,
                actorId: $actorId,
                notes: $notes,
            );

            $this->syncFromLines($settlement);

            return $this->syncOrderFinancialState($order->fresh(['settlement.lines']), $actorId);
        });
    }

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

    public function recordSupplementalPayment(Order $order, float $amount, int $actorId, ?string $notes = null): void
    {
        $settlement = $order->settlement()->with('beneficiaryUser.mealBenefitProfile', 'chargeAccountUser.mealBenefitProfile')->first();

        if (!$settlement || $amount <= 0) {
            return;
        }

        $user = $settlement->beneficiaryUser;
        $profile = $user?->mealBenefitProfile;

        if (!$user) {
            return;
        }

        $period = $this->mealBenefitService->currentPeriodBounds(now(), $profile);

        $this->mealBenefitLedgerService->record(
            user: $user,
            entryType: MealBenefitLedgerEntryType::SupplementalPayment,
            amount: $amount,
            profile: $profile,
            order: $order,
            period: $period,
            notes: $notes ?: "دفع تكميلي على الطلب {$order->order_number}",
            actorId: $actorId,
        );
    }

    private function prepareOrderForSettlement(Order $order, int $actorId): Order
    {
        $order->loadMissing('drawerSession', 'activeItems');

        $this->drawerSessionService->assertSessionNotUnderReconciliationForActor($order->drawerSession, $actorId);

        if ($order->status->isFinal()) {
            throw OrderException::invalidTransition($order->status, $order->status);
        }

        if ($order->status !== OrderStatus::Pending) {
            throw OrderException::invalidTransition($order->status, $order->status);
        }

        if ($order->activeItems->isEmpty()) {
            throw OrderException::emptyOrder();
        }

        if ((float) $order->paid_amount > 0 || $order->payments()->exists()) {
            throw OrderException::settlementNotAllowedAfterPayment();
        }

        MealBenefitLedgerEntry::query()
            ->where('order_id', $order->id)
            ->delete();

        $order->settlement()?->delete();

        return $order->fresh(['items', 'activeItems', 'drawerSession']);
    }

    private function freshSettlement(Order $order, int $actorId, array $attributes = []): OrderSettlement
    {
        return $order->settlement()->create(array_merge([
            'settlement_type' => OrderSettlementType::Standard,
            'commercial_total_amount' => (float) $order->total,
            'covered_amount' => 0,
            'remaining_payable_amount' => (float) $order->total,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ], $attributes));
    }

    private function createFreeMealSettlementLines(
        OrderSettlement $settlement,
        User $employee,
        UserMealBenefitProfile $profile,
        Collection $eligibleItems,
        array $summary,
        array $period,
        int $actorId,
        ?string $notes = null,
    ): void {
        $remainingCount = (int) $summary['free_meal_count_remaining'];
        $remainingAmount = (float) $summary['free_meal_amount_remaining'];

        $coverageLines = $this->mealBenefitService->calculateFreeMealCoverage($eligibleItems, $profile, [
            'free_meal_count_remaining' => $remainingCount,
            'free_meal_amount_remaining' => $remainingAmount,
        ]);

        foreach ($coverageLines as $lineData) {
            $orderItem = $lineData['order_item'];
            $line = $settlement->lines()->create([
                'order_id' => $settlement->order_id,
                'line_type' => $lineData['line_type'],
                'user_id' => $employee->id,
                'profile_id' => $profile->id,
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $lineData['menu_item_id'],
                'eligible_amount' => $lineData['eligible_total'],
                'covered_amount' => $lineData['covered_amount'],
                'covered_quantity' => $lineData['covered_quantity'],
                'benefit_period_start' => $period['start'],
                'benefit_period_end' => $period['end'],
                'notes' => $notes,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            if ($lineData['covered_amount'] > 0 || $lineData['meals_count'] > 0) {
                $this->mealBenefitLedgerService->record(
                    user: $employee,
                    entryType: MealBenefitLedgerEntryType::FreeMealUsage,
                    amount: $lineData['covered_amount'],
                    mealsCount: $lineData['meals_count'],
                    profile: $profile,
                    order: $settlement->order,
                    settlementLine: $line,
                    period: $period,
                    notes: $notes ?: "استخدام ميزة الوجبة المجانية في الطلب {$settlement->order->order_number}",
                    actorId: $actorId,
                );
            }
        }
    }

    private function syncOrderFinancialState(Order $order, int $actorId): Order
    {
        $order->loadMissing(['settlement', 'items.menuItem.recipeLines.inventoryItem', 'customer']);

        $newPaymentStatus = match (true) {
            $order->isFullyPaid() => PaymentStatus::Paid,
            $order->coveredAmount() > 0 || (float) $order->paid_amount > 0 => PaymentStatus::Partial,
            default => PaymentStatus::Unpaid,
        };

        $wasPaid = $order->payment_status === PaymentStatus::Paid;

        if ($order->payment_status !== $newPaymentStatus) {
            $order->update([
                'payment_status' => $newPaymentStatus,
                'updated_by' => $actorId,
            ]);
            $order->refresh();
        }

        if ($order->isFullyPaid()) {
            $this->recipeInventoryService->deductPendingForOrder($order->fresh(['items.menuItem.recipeLines.inventoryItem']), $actorId);

            if ($order->status === OrderStatus::Pending) {
                $order->transitionTo(OrderStatus::Confirmed, $actorId);
                $order->refresh();
            }

            if (!$wasPaid && $order->customer_id) {
                $order->customer?->recordOrder((float) $order->total);
            }
        }

        return $order->fresh([
            'settlement.lines.orderItem',
            'settlement.beneficiaryUser',
            'settlement.chargeAccountUser',
        ]);
    }
}
