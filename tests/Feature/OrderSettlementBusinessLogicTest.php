<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\DTOs\ProcessPaymentData;
use App\Enums\DrawerSessionStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\MealBenefitLedgerEntryType;
use App\Enums\OrderSettlementType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserMealBenefitFreeMealType;
use App\Enums\UserMealBenefitPeriodType;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\InventoryItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemRecipeLine;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Services\OrderCreationService;
use App\Services\OrderPaymentService;
use App\Services\OrderSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSettlementBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_charge_order_is_fully_covered_without_immediate_payment(): void
    {
        $fixture = $this->createOrderFixture([
            'eligible_total' => 120,
            'non_eligible_total' => 0,
            'eligible_quantity' => 1,
        ]);
        $cashier = $fixture['cashier'];
        $admin = $fixture['admin'];
        $inventoryItem = $fixture['inventory_item'];
        $order = $fixture['order'];
        $eligibleItem = $fixture['eligible_order_item'];

        $profile = UserMealBenefitProfile::create([
            'user_id' => $admin->id,
            'is_active' => true,
            'can_receive_owner_charge_orders' => true,
        ]);

        $settledOrder = app(OrderSettlementService::class)->applyOwnerCharge(
            order: $order->fresh(),
            chargeAccount: $admin,
            actorId: $cashier->id,
        );

        $this->assertEquals(OrderSettlementType::OwnerCharge, $settledOrder->settlement->settlement_type);
        $this->assertSame('120.00', $settledOrder->settlement->commercial_total_amount);
        $this->assertSame('120.00', $settledOrder->settlement->covered_amount);
        $this->assertSame('0.00', $settledOrder->settlement->remaining_payable_amount);
        $this->assertSame(PaymentStatus::Paid, $settledOrder->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $settledOrder->status);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseHas('meal_benefit_ledger_entries', [
            'user_id' => $admin->id,
            'profile_id' => $profile->id,
            'order_id' => $settledOrder->id,
            'entry_type' => MealBenefitLedgerEntryType::OwnerChargeUsage->value,
            'amount' => '120.00',
        ]);

        $inventoryItem->refresh();
        $this->assertSame('4.800', $inventoryItem->current_stock);
        $this->assertNotNull($eligibleItem->fresh()->stock_deducted_at);
    }

    public function test_employee_monthly_allowance_can_fully_cover_order(): void
    {
        $fixture = $this->createOrderFixture([
            'eligible_total' => 90,
            'non_eligible_total' => 0,
            'eligible_quantity' => 1,
        ], employeeRole: 'cashier');
        $cashier = $fixture['cashier'];
        $employee = $fixture['employee'];
        $inventoryItem = $fixture['inventory_item'];
        $order = $fixture['order'];
        $eligibleItem = $fixture['eligible_order_item'];

        $profile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 150,
        ]);

        $settledOrder = app(OrderSettlementService::class)->applyEmployeeMonthlyAllowance(
            order: $order->fresh(),
            employee: $employee,
            actorId: $cashier->id,
        );

        $this->assertEquals(OrderSettlementType::EmployeeAllowance, $settledOrder->settlement->settlement_type);
        $this->assertSame('90.00', $settledOrder->settlement->covered_amount);
        $this->assertSame('0.00', $settledOrder->settlement->remaining_payable_amount);
        $this->assertSame(PaymentStatus::Paid, $settledOrder->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $settledOrder->status);
        $this->assertDatabaseHas('meal_benefit_ledger_entries', [
            'user_id' => $employee->id,
            'profile_id' => $profile->id,
            'order_id' => $settledOrder->id,
            'entry_type' => MealBenefitLedgerEntryType::MonthlyAllowanceUsage->value,
            'amount' => '90.00',
        ]);

        $inventoryItem->refresh();
        $this->assertSame('4.800', $inventoryItem->current_stock);
        $this->assertNotNull($eligibleItem->fresh()->stock_deducted_at);
    }

    public function test_employee_monthly_allowance_can_partially_cover_order_and_leave_difference_for_payment(): void
    {
        $fixture = $this->createOrderFixture([
            'eligible_total' => 120,
            'non_eligible_total' => 0,
            'eligible_quantity' => 1,
        ], employeeRole: 'cashier');
        $cashier = $fixture['cashier'];
        $employee = $fixture['employee'];
        $inventoryItem = $fixture['inventory_item'];
        $order = $fixture['order'];
        $eligibleItem = $fixture['eligible_order_item'];

        $profile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 50,
        ]);

        $partiallySettledOrder = app(OrderSettlementService::class)->applyEmployeeMonthlyAllowance(
            order: $order->fresh(),
            employee: $employee,
            actorId: $cashier->id,
        );

        $this->assertSame(PaymentStatus::Partial, $partiallySettledOrder->payment_status);
        $this->assertSame(OrderStatus::Pending, $partiallySettledOrder->status);
        $this->assertSame('50.00', $partiallySettledOrder->settlement->covered_amount);
        $this->assertSame('70.00', $partiallySettledOrder->settlement->remaining_payable_amount);
        $this->assertNull($eligibleItem->fresh()->stock_deducted_at);

        $paidOrder = app(OrderPaymentService::class)->processPayment(
            order: $partiallySettledOrder->fresh(),
            payments: [
                new ProcessPaymentData(method: PaymentMethod::Cash, amount: 70),
            ],
            actorId: $cashier->id,
        );

        $this->assertSame(PaymentStatus::Paid, $paidOrder->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $paidOrder->status);
        $this->assertSame('70.00', $paidOrder->paid_amount);
        $this->assertDatabaseHas('meal_benefit_ledger_entries', [
            'user_id' => $employee->id,
            'profile_id' => $profile->id,
            'order_id' => $paidOrder->id,
            'entry_type' => MealBenefitLedgerEntryType::SupplementalPayment->value,
            'amount' => '70.00',
        ]);

        $inventoryItem->refresh();
        $this->assertSame('4.800', $inventoryItem->current_stock);
        $this->assertNotNull($eligibleItem->fresh()->stock_deducted_at);
    }

    public function test_employee_allowance_uses_weekly_period_for_settlement_and_supplemental_payment(): void
    {
        $fixture = $this->createOrderFixture([
            'eligible_total' => 120,
            'non_eligible_total' => 0,
            'eligible_quantity' => 1,
        ], employeeRole: 'cashier');
        $cashier = $fixture['cashier'];
        $employee = $fixture['employee'];
        $order = $fixture['order'];

        $profile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 50,
            'benefit_period_type' => UserMealBenefitPeriodType::Weekly,
        ]);

        $partiallySettledOrder = app(OrderSettlementService::class)->applyEmployeeMonthlyAllowance(
            order: $order->fresh(),
            employee: $employee,
            actorId: $cashier->id,
        );

        $this->assertDatabaseHas('order_settlement_lines', [
            'order_id' => $partiallySettledOrder->id,
            'profile_id' => $profile->id,
            'benefit_period_start' => now()->startOfWeek()->toDateString(),
            'benefit_period_end' => now()->endOfWeek()->toDateString(),
        ]);

        app(OrderPaymentService::class)->processPayment(
            order: $partiallySettledOrder->fresh(),
            payments: [
                new ProcessPaymentData(method: PaymentMethod::Cash, amount: 70),
            ],
            actorId: $cashier->id,
        );

        $this->assertDatabaseHas('meal_benefit_ledger_entries', [
            'user_id' => $employee->id,
            'profile_id' => $profile->id,
            'order_id' => $order->id,
            'entry_type' => MealBenefitLedgerEntryType::SupplementalPayment->value,
            'amount' => '70.00',
            'benefit_period_start' => now()->startOfWeek()->toDateString(),
            'benefit_period_end' => now()->endOfWeek()->toDateString(),
        ]);
    }

    public function test_employee_free_meal_meals_count_covers_allowed_quantities_monthly(): void
    {
        $fixture = $this->createOrderFixture([
            'eligible_total' => 80,
            'non_eligible_total' => 0,
            'eligible_quantity' => 2,
        ], employeeRole: 'cashier');
        $cashier = $fixture['cashier'];
        $employee = $fixture['employee'];
        $inventoryItem = $fixture['inventory_item'];
        $order = $fixture['order'];
        $eligibleItem = $fixture['eligible_order_item'];

        $profile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Count,
            'free_meal_monthly_count' => 2,
        ]);
        $profile->allowedMenuItems()->sync([$eligibleItem->menu_item_id]);

        $settledOrder = app(OrderSettlementService::class)->applyEmployeeFreeMealBenefit(
            order: $order->fresh(),
            employee: $employee,
            actorId: $cashier->id,
        );

        $this->assertEquals(OrderSettlementType::EmployeeFreeMeal, $settledOrder->settlement->settlement_type);
        $this->assertSame('80.00', $settledOrder->settlement->covered_amount);
        $this->assertSame('0.00', $settledOrder->settlement->remaining_payable_amount);
        $this->assertDatabaseHas('order_settlement_lines', [
            'order_id' => $settledOrder->id,
            'line_type' => 'employee_free_meal_count',
            'covered_quantity' => 2,
            'covered_amount' => '80.00',
        ]);
        $this->assertDatabaseHas('meal_benefit_ledger_entries', [
            'user_id' => $employee->id,
            'profile_id' => $profile->id,
            'order_id' => $settledOrder->id,
            'entry_type' => MealBenefitLedgerEntryType::FreeMealUsage->value,
            'amount' => '80.00',
            'meals_count' => 2,
        ]);

        $inventoryItem->refresh();
        $this->assertSame('4.600', $inventoryItem->current_stock);
    }

    public function test_employee_free_meal_amount_limit_covers_only_available_amount(): void
    {
        $fixture = $this->createOrderFixture([
            'eligible_total' => 80,
            'non_eligible_total' => 0,
            'eligible_quantity' => 1,
        ], employeeRole: 'cashier');
        $cashier = $fixture['cashier'];
        $employee = $fixture['employee'];
        $inventoryItem = $fixture['inventory_item'];
        $order = $fixture['order'];
        $eligibleItem = $fixture['eligible_order_item'];

        $profile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Amount,
            'free_meal_monthly_amount' => 50,
        ]);
        $profile->allowedMenuItems()->sync([$eligibleItem->menu_item_id]);

        $settledOrder = app(OrderSettlementService::class)->applyEmployeeFreeMealBenefit(
            order: $order->fresh(),
            employee: $employee,
            actorId: $cashier->id,
        );

        $this->assertSame(PaymentStatus::Partial, $settledOrder->payment_status);
        $this->assertSame('50.00', $settledOrder->settlement->covered_amount);
        $this->assertSame('30.00', $settledOrder->settlement->remaining_payable_amount);
        $this->assertDatabaseHas('meal_benefit_ledger_entries', [
            'user_id' => $employee->id,
            'profile_id' => $profile->id,
            'order_id' => $settledOrder->id,
            'entry_type' => MealBenefitLedgerEntryType::FreeMealUsage->value,
            'amount' => '50.00',
        ]);
        $this->assertNull($eligibleItem->fresh()->stock_deducted_at);
    }

    public function test_mixed_eligible_and_non_eligible_items_cover_only_eligible_portion(): void
    {
        $fixture = $this->createOrderFixture([
            'eligible_total' => 80,
            'non_eligible_total' => 40,
            'eligible_quantity' => 1,
        ], employeeRole: 'cashier');
        $cashier = $fixture['cashier'];
        $employee = $fixture['employee'];
        $inventoryItem = $fixture['inventory_item'];
        $order = $fixture['order'];
        $eligibleItem = $fixture['eligible_order_item'];
        $nonEligibleItem = $fixture['non_eligible_order_item'];

        $profile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Amount,
            'free_meal_monthly_amount' => 50,
        ]);
        $profile->allowedMenuItems()->sync([$eligibleItem->menu_item_id]);

        $partiallySettledOrder = app(OrderSettlementService::class)->applyEmployeeFreeMealBenefit(
            order: $order->fresh(),
            employee: $employee,
            actorId: $cashier->id,
        );

        $this->assertSame('120.00', $partiallySettledOrder->settlement->commercial_total_amount);
        $this->assertSame('50.00', $partiallySettledOrder->settlement->covered_amount);
        $this->assertSame('70.00', $partiallySettledOrder->settlement->remaining_payable_amount);
        $this->assertSame(
            '80.00',
            number_format((float) $partiallySettledOrder->settlement->lines->sum('eligible_amount'), 2, '.', '')
        );
        $this->assertCount(1, $partiallySettledOrder->settlement->lines);
        $this->assertSame($eligibleItem->id, $partiallySettledOrder->settlement->lines->first()->order_item_id);

        $paidOrder = app(OrderPaymentService::class)->processPayment(
            order: $partiallySettledOrder->fresh(),
            payments: [
                new ProcessPaymentData(method: PaymentMethod::Cash, amount: 70),
            ],
            actorId: $cashier->id,
        );

        $this->assertSame(PaymentStatus::Paid, $paidOrder->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $paidOrder->status);
        $this->assertDatabaseHas('meal_benefit_ledger_entries', [
            'user_id' => $employee->id,
            'order_id' => $paidOrder->id,
            'entry_type' => MealBenefitLedgerEntryType::SupplementalPayment->value,
            'amount' => '70.00',
        ]);

        $inventoryItem->refresh();
        $this->assertSame('4.700', $inventoryItem->current_stock);
        $this->assertNotNull($eligibleItem->fresh()->stock_deducted_at);
        $this->assertNotNull($nonEligibleItem->fresh()->stock_deducted_at);
    }

    private function createOrderFixture(array $prices, string $employeeRole = 'employee'): array
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier',
            'is_active' => true,
        ]);

        $employee = User::factory()->create([
            'name' => 'Employee',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin Owner',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(['name' => 'cashier'], ['display_name' => 'Cashier']);
        $employeeRoleModel = Role::firstOrCreate(['name' => $employeeRole], ['display_name' => ucfirst($employeeRole)]);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);

        $cashier->roles()->syncWithoutDetaching([$cashierRole->id]);
        $employee->roles()->syncWithoutDetaching([$employeeRoleModel->id]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-SETTLEMENT-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS-Settlement',
            'identifier' => uniqid('POS-SET-', true),
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => uniqid('DRW-SET-', true),
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 500,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'مخزون اختبار',
            'unit' => 'كجم',
            'unit_cost' => 100,
            'current_stock' => 5,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'name' => 'تصنيف اختبار',
            'is_active' => true,
        ]);

        $eligibleMenuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'صنف مؤهل',
            'type' => 'simple',
            'base_price' => $prices['eligible_total'] / max(1, ($prices['eligible_quantity'] ?? 1)),
            'is_available' => true,
            'is_active' => true,
        ]);

        MenuItemRecipeLine::create([
            'menu_item_id' => $eligibleMenuItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 200,
            'unit' => 'جم',
            'unit_conversion_rate' => 0.001,
        ]);

        $nonEligibleMenuItem = null;
        if (($prices['non_eligible_total'] ?? 0) > 0) {
            $nonEligibleMenuItem = MenuItem::create([
                'category_id' => $category->id,
                'name' => 'صنف غير مؤهل',
                'type' => 'simple',
                'base_price' => $prices['non_eligible_total'],
                'is_available' => true,
                'is_active' => true,
            ]);

            MenuItemRecipeLine::create([
                'menu_item_id' => $nonEligibleMenuItem->id,
                'inventory_item_id' => $inventoryItem->id,
                'quantity' => 100,
                'unit' => 'جم',
                'unit_conversion_rate' => 0.001,
            ]);
        }

        $order = app(OrderCreationService::class)->create(
            cashier: $cashier,
            data: CreateOrderData::fromArray([
                'type' => OrderType::Takeaway->value,
                'source' => OrderSource::Pos->value,
                'tax_rate' => 0,
            ]),
        );

        $eligibleOrderItem = app(OrderCreationService::class)->addItem(
            order: $order,
            data: AddOrderItemData::fromArray([
                'menu_item_id' => $eligibleMenuItem->id,
                'quantity' => $prices['eligible_quantity'] ?? 1,
            ]),
            actorId: $cashier->id,
        );

        $nonEligibleOrderItem = null;
        if ($nonEligibleMenuItem) {
            $nonEligibleOrderItem = app(OrderCreationService::class)->addItem(
                order: $order->fresh(),
                data: AddOrderItemData::fromArray([
                    'menu_item_id' => $nonEligibleMenuItem->id,
                    'quantity' => 1,
                ]),
                actorId: $cashier->id,
            );
        }

        return [
            'cashier' => $cashier,
            'employee' => $employee,
            'admin' => $admin,
            'inventory_item' => $inventoryItem,
            'order' => $order->fresh(['items']),
            'eligible_order_item' => $eligibleOrderItem->fresh(),
            'non_eligible_order_item' => $nonEligibleOrderItem?->fresh(),
        ];
    }
}
