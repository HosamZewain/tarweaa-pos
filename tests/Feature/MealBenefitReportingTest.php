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
use App\Models\MealBenefitLedgerEntry;
use App\Models\Order;
use App\Models\OrderSettlement;
use App\Models\OrderSettlementLine;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Services\MealBenefitLedgerService;
use App\Services\MealBenefitReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MealBenefitReportingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $cashier;

    protected Shift $shift;

    protected PosDevice $device;

    protected CashierDrawerSession $drawerSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->first()
            ?? User::factory()->create([
                'email' => 'admin@pos.com',
                'is_active' => true,
            ]);

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator', 'is_active' => true],
        );

        if (!$this->adminUser->roles->contains($adminRole->id)) {
            $this->adminUser->roles()->attach($adminRole->id);
        }

        $this->cashier = User::factory()->create([
            'name' => 'Cashier Reporter',
            'username' => 'cashier-reporter',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier', 'is_active' => true],
        );
        $this->cashier->roles()->attach($cashierRole->id);

        $this->shift = Shift::create([
            'shift_number' => 'SHIFT-MEAL-REPORT-001',
            'status' => ShiftStatus::Open->value,
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $this->device = PosDevice::create([
            'name' => 'POS Report Device',
            'identifier' => 'POS-MEAL-REPORT-1',
            'is_active' => true,
        ]);

        $this->drawerSession = CashierDrawerSession::create([
            'session_number' => 'DRAWER-MEAL-REPORT-001',
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->adminUser->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now(),
        ]);
    }

    public function test_monthly_reports_cover_owner_allowance_free_meal_and_mixed_differences(): void
    {
        $reference = Carbon::create(2026, 3, 20, 12, 0, 0);
        $owner = $this->createRoleUser('Owner Account', 'owner-account', 'owner');
        $employee = $this->createRoleUser('Benefit Employee', 'benefit-employee', 'cashier');

        $ownerProfile = UserMealBenefitProfile::create([
            'user_id' => $owner->id,
            'is_active' => true,
            'can_receive_owner_charge_orders' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $employeeProfile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 200,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Count,
            'free_meal_monthly_count' => 5,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $ownerOrder = $this->createOrder('ORD-OWNER-001', 150, $reference->copy()->subDays(4));
        $ownerSettlement = $this->createSettlement(
            order: $ownerOrder,
            settlementType: OrderSettlementType::OwnerCharge,
            coveredAmount: 150,
            remainingAmount: 0,
            beneficiaryUserId: null,
            chargeAccountUserId: $owner->id,
            createdAt: $reference->copy()->subDays(4),
        );
        $ownerLine = $this->createSettlementLine(
            settlement: $ownerSettlement,
            lineType: OrderSettlementLineType::OwnerCharge,
            userId: $owner->id,
            profileId: $ownerProfile->id,
            eligibleAmount: 150,
            coveredAmount: 150,
            createdAt: $reference->copy()->subDays(4),
        );
        $this->createLedgerEntry(
            user: $owner,
            entryType: MealBenefitLedgerEntryType::OwnerChargeUsage,
            amount: 150,
            profile: $ownerProfile,
            order: $ownerOrder,
            settlementLine: $ownerLine,
            createdAt: $reference->copy()->subDays(4),
            notes: 'Owner charge statement line',
        );

        $allowanceOrder = $this->createOrder('ORD-ALLOW-001', 120, $reference->copy()->subDays(2), $employee);
        $allowanceSettlement = $this->createSettlement(
            order: $allowanceOrder,
            settlementType: OrderSettlementType::EmployeeAllowance,
            coveredAmount: 80,
            remainingAmount: 40,
            beneficiaryUserId: $employee->id,
            chargeAccountUserId: null,
            createdAt: $reference->copy()->subDays(2),
        );
        $allowanceLine = $this->createSettlementLine(
            settlement: $allowanceSettlement,
            lineType: OrderSettlementLineType::EmployeeMonthlyAllowance,
            userId: $employee->id,
            profileId: $employeeProfile->id,
            eligibleAmount: 120,
            coveredAmount: 80,
            createdAt: $reference->copy()->subDays(2),
            periodReference: $reference,
        );
        $this->createLedgerEntry(
            user: $employee,
            entryType: MealBenefitLedgerEntryType::MonthlyAllowanceUsage,
            amount: 80,
            profile: $employeeProfile,
            order: $allowanceOrder,
            settlementLine: $allowanceLine,
            createdAt: $reference->copy()->subDays(2),
            notes: 'Monthly allowance usage',
            periodReference: $reference,
        );
        $this->createLedgerEntry(
            user: $employee,
            entryType: MealBenefitLedgerEntryType::SupplementalPayment,
            amount: 40,
            profile: $employeeProfile,
            order: $allowanceOrder,
            settlementLine: null,
            createdAt: $reference->copy()->subDays(1),
            notes: 'Allowance difference paid',
            periodReference: $reference,
        );

        $freeMealOrder = $this->createOrder('ORD-FREE-001', 60, $reference->copy()->subDay(), $employee);
        $freeMealSettlement = $this->createSettlement(
            order: $freeMealOrder,
            settlementType: OrderSettlementType::EmployeeFreeMeal,
            coveredAmount: 60,
            remainingAmount: 0,
            beneficiaryUserId: $employee->id,
            chargeAccountUserId: null,
            createdAt: $reference->copy()->subDay(),
        );
        $freeMealLine = $this->createSettlementLine(
            settlement: $freeMealSettlement,
            lineType: OrderSettlementLineType::EmployeeFreeMealCount,
            userId: $employee->id,
            profileId: $employeeProfile->id,
            eligibleAmount: 60,
            coveredAmount: 60,
            coveredQuantity: 2,
            createdAt: $reference->copy()->subDay(),
            periodReference: $reference,
        );
        $this->createLedgerEntry(
            user: $employee,
            entryType: MealBenefitLedgerEntryType::FreeMealUsage,
            amount: 60,
            mealsCount: 2,
            profile: $employeeProfile,
            order: $freeMealOrder,
            settlementLine: $freeMealLine,
            createdAt: $reference->copy()->subDay(),
            notes: 'Free meal usage',
            periodReference: $reference,
        );

        $report = app(MealBenefitReportService::class)->buildMonthlyReport($reference);

        $this->assertSame(1, $report['owner_charge_statement']['orders_count']);
        $this->assertSame(150.0, $report['owner_charge_statement']['total_amount']);
        $this->assertSame('Owner charge statement line', $report['owner_charge_statement']['rows'][0]['notes']);

        $allowanceRow = collect($report['allowance_report']['rows'])->firstWhere('user_name', $employee->name);
        $this->assertNotNull($allowanceRow);
        $this->assertSame(200.0, $allowanceRow['configured_monthly_allowance']);
        $this->assertSame(80.0, $allowanceRow['consumed_amount']);
        $this->assertSame(120.0, $allowanceRow['remaining_amount']);
        $this->assertSame(1, $allowanceRow['covered_orders_count']);
        $this->assertSame(40.0, $allowanceRow['paid_differences_amount']);

        $freeMealRow = collect($report['free_meal_report']['rows'])->firstWhere('user_name', $employee->name);
        $this->assertNotNull($freeMealRow);
        $this->assertSame('عدد وجبات', $freeMealRow['benefit_type']);
        $this->assertSame('5 وجبة', $freeMealRow['configured_limit']);
        $this->assertSame(60.0, $freeMealRow['consumed_amount']);
        $this->assertSame(2, $freeMealRow['consumed_count']);
        $this->assertSame(3, $freeMealRow['remaining_count']);

        $this->assertSame(1, $report['mixed_coverage_report']['totals']['orders_count']);
        $this->assertSame(80.0, $report['mixed_coverage_report']['totals']['covered_amount']);
        $this->assertSame(40.0, $report['mixed_coverage_report']['totals']['paid_differences_amount']);
        $this->assertSame('ORD-ALLOW-001', $report['mixed_coverage_report']['rows'][0]['order_number']);
    }

    public function test_monthly_reset_logic_uses_calendar_month_and_preserves_history(): void
    {
        $march = Carbon::create(2026, 3, 15, 10, 0, 0);
        $april = Carbon::create(2026, 4, 15, 10, 0, 0);
        $employee = $this->createRoleUser('Monthly Reset Employee', 'monthly-reset-employee', 'cashier');

        $profile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 300,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Amount,
            'free_meal_monthly_amount' => 120,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $marchOrder = $this->createOrder('ORD-MARCH-001', 100, $march, $employee);
        $marchSettlement = $this->createSettlement(
            order: $marchOrder,
            settlementType: OrderSettlementType::EmployeeAllowance,
            coveredAmount: 100,
            remainingAmount: 0,
            beneficiaryUserId: $employee->id,
            chargeAccountUserId: null,
            createdAt: $march,
        );
        $marchLine = $this->createSettlementLine(
            settlement: $marchSettlement,
            lineType: OrderSettlementLineType::EmployeeMonthlyAllowance,
            userId: $employee->id,
            profileId: $profile->id,
            eligibleAmount: 100,
            coveredAmount: 100,
            createdAt: $march,
            periodReference: $march,
        );
        $this->createLedgerEntry(
            user: $employee,
            entryType: MealBenefitLedgerEntryType::MonthlyAllowanceUsage,
            amount: 100,
            profile: $profile,
            order: $marchOrder,
            settlementLine: $marchLine,
            createdAt: $march,
            notes: 'March allowance usage',
            periodReference: $march,
        );

        $aprilOrder = $this->createOrder('ORD-APRIL-001', 60, $april, $employee);
        $aprilSettlement = $this->createSettlement(
            order: $aprilOrder,
            settlementType: OrderSettlementType::EmployeeFreeMeal,
            coveredAmount: 60,
            remainingAmount: 0,
            beneficiaryUserId: $employee->id,
            chargeAccountUserId: null,
            createdAt: $april,
        );
        $aprilLine = $this->createSettlementLine(
            settlement: $aprilSettlement,
            lineType: OrderSettlementLineType::EmployeeFreeMealAmount,
            userId: $employee->id,
            profileId: $profile->id,
            eligibleAmount: 60,
            coveredAmount: 60,
            createdAt: $april,
            periodReference: $april,
        );
        $this->createLedgerEntry(
            user: $employee,
            entryType: MealBenefitLedgerEntryType::FreeMealUsage,
            amount: 60,
            profile: $profile,
            order: $aprilOrder,
            settlementLine: $aprilLine,
            createdAt: $april,
            notes: 'April free meal usage',
            periodReference: $april,
        );

        $marchReport = app(MealBenefitReportService::class)->buildMonthlyReport($march, $employee->id);
        $aprilReport = app(MealBenefitReportService::class)->buildMonthlyReport($april, $employee->id);

        $this->assertSame(100.0, $marchReport['selected_user_summary']['monthly_allowance_used']);
        $this->assertSame(200.0, $marchReport['selected_user_summary']['monthly_allowance_remaining']);
        $this->assertCount(1, $marchReport['entries']);
        $this->assertSame('March allowance usage', $marchReport['entries'][0]['notes']);

        $this->assertSame(0.0, $aprilReport['selected_user_summary']['monthly_allowance_used']);
        $this->assertSame(300.0, $aprilReport['selected_user_summary']['monthly_allowance_remaining']);
        $this->assertSame(60.0, $aprilReport['selected_user_summary']['free_meal_amount_used']);
        $this->assertSame(60.0, $aprilReport['selected_user_summary']['free_meal_amount_remaining']);
        $this->assertCount(1, $aprilReport['entries']);
        $this->assertSame('April free meal usage', $aprilReport['entries'][0]['notes']);
    }

    public function test_admin_meal_benefits_report_page_renders_all_new_sections(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/meal-benefits-report')
            ->assertSuccessful()
            ->assertSee('كشف التحميل على المالك / الإدارة')
            ->assertSee('تقرير البدل الشهري للموظفين')
            ->assertSee('تقرير الوجبات المجانية')
            ->assertSee('تقرير التغطية الجزئية وفروق السداد');
    }

    private function createRoleUser(string $name, string $username, string $roleName): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'username' => $username,
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['display_name' => ucfirst($roleName), 'is_active' => true],
        );

        $user->roles()->attach($role->id);

        return $user;
    }

    private function createOrder(string $orderNumber, float $total, Carbon $createdAt, ?User $beneficiary = null): Order
    {
        $order = Order::create([
            'order_number' => $orderNumber,
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Confirmed,
            'source' => OrderSource::Pos,
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
            'drawer_session_id' => $this->drawerSession->id,
            'pos_device_id' => $this->device->id,
            'customer_id' => null,
            'customer_name' => $beneficiary?->name,
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => $total,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
            'confirmed_at' => $createdAt,
            'created_by' => $this->cashier->id,
            'updated_by' => $this->cashier->id,
        ]);

        Order::query()->whereKey($order->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $order->fresh();
    }

    private function createSettlement(
        Order $order,
        OrderSettlementType $settlementType,
        float $coveredAmount,
        float $remainingAmount,
        ?int $beneficiaryUserId,
        ?int $chargeAccountUserId,
        Carbon $createdAt,
    ): OrderSettlement {
        $settlement = OrderSettlement::create([
            'order_id' => $order->id,
            'settlement_type' => $settlementType,
            'beneficiary_user_id' => $beneficiaryUserId,
            'charge_account_user_id' => $chargeAccountUserId,
            'commercial_total_amount' => $order->total,
            'covered_amount' => $coveredAmount,
            'remaining_payable_amount' => $remainingAmount,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        OrderSettlement::query()->whereKey($settlement->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $settlement->fresh();
    }

    private function createSettlementLine(
        OrderSettlement $settlement,
        OrderSettlementLineType $lineType,
        ?int $userId,
        ?int $profileId,
        float $eligibleAmount,
        float $coveredAmount,
        Carbon $createdAt,
        ?Carbon $periodReference = null,
        ?int $coveredQuantity = null,
    ): OrderSettlementLine {
        $line = OrderSettlementLine::create([
            'order_settlement_id' => $settlement->id,
            'order_id' => $settlement->order_id,
            'line_type' => $lineType,
            'user_id' => $userId,
            'profile_id' => $profileId,
            'eligible_amount' => $eligibleAmount,
            'covered_amount' => $coveredAmount,
            'covered_quantity' => $coveredQuantity,
            'benefit_period_start' => $periodReference?->copy()->startOfMonth()->toDateString(),
            'benefit_period_end' => $periodReference?->copy()->endOfMonth()->toDateString(),
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        OrderSettlementLine::query()->whereKey($line->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $line->fresh();
    }

    private function createLedgerEntry(
        User $user,
        MealBenefitLedgerEntryType $entryType,
        float $amount,
        UserMealBenefitProfile $profile,
        Order $order,
        ?OrderSettlementLine $settlementLine,
        Carbon $createdAt,
        string $notes,
        ?Carbon $periodReference = null,
        int $mealsCount = 0,
    ): MealBenefitLedgerEntry {
        $entry = app(MealBenefitLedgerService::class)->record(
            user: $user,
            entryType: $entryType,
            amount: $amount,
            mealsCount: $mealsCount,
            profile: $profile,
            order: $order,
            settlementLine: $settlementLine,
            period: $periodReference ? [
                'start' => $periodReference->copy()->startOfMonth()->toDateString(),
                'end' => $periodReference->copy()->endOfMonth()->toDateString(),
            ] : null,
            notes: $notes,
            actorId: $this->adminUser->id,
        );

        MealBenefitLedgerEntry::query()->whereKey($entry->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $entry->fresh();
    }
}
