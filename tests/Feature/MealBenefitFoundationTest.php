<?php

namespace Tests\Feature;

use App\Enums\MealBenefitLedgerEntryType;
use App\Enums\OrderSettlementLineType;
use App\Enums\OrderSettlementType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserMealBenefitFreeMealType;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MealBenefitLedgerEntry;
use App\Models\Order;
use App\Models\OrderSettlement;
use App\Models\OrderSettlementLine;
use App\Models\PosDevice;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Services\MealBenefitService;
use App\Services\OrderSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealBenefitFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_meal_benefit_service_summarizes_current_month_usage(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $profile = UserMealBenefitProfile::create([
            'user_id' => $user->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 300,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Count,
            'free_meal_monthly_count' => 10,
            'free_meal_monthly_amount' => 250,
        ]);

        MealBenefitLedgerEntry::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'entry_type' => MealBenefitLedgerEntryType::MonthlyAllowanceUsage,
            'amount' => 90,
            'benefit_period_start' => now()->startOfMonth()->toDateString(),
            'benefit_period_end' => now()->endOfMonth()->toDateString(),
        ]);

        MealBenefitLedgerEntry::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'entry_type' => MealBenefitLedgerEntryType::FreeMealUsage,
            'amount' => 45,
            'meals_count' => 2,
            'benefit_period_start' => now()->startOfMonth()->toDateString(),
            'benefit_period_end' => now()->endOfMonth()->toDateString(),
        ]);

        MealBenefitLedgerEntry::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'entry_type' => MealBenefitLedgerEntryType::MonthlyAllowanceUsage,
            'amount' => 500,
            'benefit_period_start' => now()->startOfMonth()->subMonthNoOverflow()->toDateString(),
            'benefit_period_end' => now()->endOfMonth()->subMonthNoOverflow()->toDateString(),
        ]);

        $summary = app(MealBenefitService::class)->getMonthlySummary($user);

        $this->assertSame(90.0, $summary['monthly_allowance_used']);
        $this->assertSame(210.0, $summary['monthly_allowance_remaining']);
        $this->assertSame(45.0, $summary['free_meal_amount_used']);
        $this->assertSame(205.0, $summary['free_meal_amount_remaining']);
        $this->assertSame(2, $summary['free_meal_count_used']);
        $this->assertSame(8, $summary['free_meal_count_remaining']);
    }

    public function test_order_settlement_service_syncs_summary_from_lines(): void
    {
        $cashier = User::factory()->create(['is_active' => true]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-BEN-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Benefit',
            'identifier' => 'POS-BEN-001',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-BEN-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now(),
        ]);

        $category = MenuCategory::create([
            'name' => 'وجبات الموظفين',
            'is_active' => true,
        ]);

        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'وجبة',
            'type' => 'simple',
            'base_price' => 80,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-BEN-0001',
            'type' => OrderType::Pickup,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'subtotal' => 120,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 120,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        $profile = UserMealBenefitProfile::create([
            'user_id' => $cashier->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 200,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Amount,
            'free_meal_monthly_amount' => 100,
        ]);

        $profile->allowedMenuItems()->sync([$item->id]);

        $settlement = OrderSettlement::create([
            'order_id' => $order->id,
            'settlement_type' => OrderSettlementType::Standard,
            'beneficiary_user_id' => $cashier->id,
            'commercial_total_amount' => $order->total,
            'covered_amount' => 0,
            'remaining_payable_amount' => $order->total,
        ]);

        OrderSettlementLine::create([
            'order_settlement_id' => $settlement->id,
            'order_id' => $order->id,
            'line_type' => OrderSettlementLineType::EmployeeMonthlyAllowance,
            'user_id' => $cashier->id,
            'profile_id' => $profile->id,
            'menu_item_id' => $item->id,
            'eligible_amount' => 70,
            'covered_amount' => 70,
            'benefit_period_start' => now()->startOfMonth()->toDateString(),
            'benefit_period_end' => now()->endOfMonth()->toDateString(),
        ]);

        OrderSettlementLine::create([
            'order_settlement_id' => $settlement->id,
            'order_id' => $order->id,
            'line_type' => OrderSettlementLineType::EmployeeFreeMealAmount,
            'user_id' => $cashier->id,
            'profile_id' => $profile->id,
            'menu_item_id' => $item->id,
            'eligible_amount' => 20,
            'covered_amount' => 20,
            'benefit_period_start' => now()->startOfMonth()->toDateString(),
            'benefit_period_end' => now()->endOfMonth()->toDateString(),
        ]);

        $fresh = app(OrderSettlementService::class)->syncFromLines($settlement);

        $this->assertSame(OrderSettlementType::MixedBenefit, $fresh->settlement_type);
        $this->assertSame('90.00', $fresh->covered_amount);
        $this->assertSame('30.00', $fresh->remaining_payable_amount);
        $this->assertTrue($profile->allowedMenuItems->contains($item));
    }
}
