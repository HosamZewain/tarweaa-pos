<?php

namespace Tests\Feature;

use App\Enums\UserMealBenefitFreeMealType;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosSettlementPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_returns_only_eligible_candidates_for_each_special_settlement_scenario(): void
    {
        $cashier = $this->createCashierWithSettlementPermission();
        $owner = $this->createUserWithRole('Owner', 'admin');
        $employeeAllowance = $this->createUserWithRole('Employee Allowance', 'employee');
        $employeeFreeMeal = $this->createUserWithRole('Employee Free Meal', 'employee');

        UserMealBenefitProfile::create([
            'user_id' => $owner->id,
            'is_active' => true,
            'can_receive_owner_charge_orders' => true,
        ]);

        UserMealBenefitProfile::create([
            'user_id' => $employeeAllowance->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 100,
        ]);

        UserMealBenefitProfile::create([
            'user_id' => $employeeFreeMeal->id,
            'is_active' => true,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Amount,
            'free_meal_monthly_amount' => 50,
        ]);

        Sanctum::actingAs($cashier);

        $this->getJson('/api/pos/settlement-users?scenario=owner_charge')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $owner->id);

        $this->getJson('/api/pos/settlement-users?scenario=employee_allowance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $employeeAllowance->id);

        $this->getJson('/api/pos/settlement-users?scenario=employee_free_meal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $employeeFreeMeal->id);
    }

    public function test_pos_owner_charge_preview_fully_covers_order_total(): void
    {
        $cashier = $this->createCashierWithSettlementPermission();
        $owner = $this->createUserWithRole('Owner', 'admin');

        UserMealBenefitProfile::create([
            'user_id' => $owner->id,
            'is_active' => true,
            'can_receive_owner_charge_orders' => true,
        ]);

        $menuItem = $this->createMenuItem('صنف اختبار', 120);

        Sanctum::actingAs($cashier);

        $this->postJson('/api/pos/settlement-preview', [
            'scenario' => 'owner_charge',
            'charge_account_user_id' => $owner->id,
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 1,
                    'modifiers' => [],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.covered_amount', 120)
            ->assertJsonPath('data.remaining_payable_amount', 0)
            ->assertJsonPath('data.charge_account_user.id', $owner->id);
    }

    public function test_pos_allowance_preview_shows_partial_coverage_and_remaining_payable(): void
    {
        $cashier = $this->createCashierWithSettlementPermission();
        $employee = $this->createUserWithRole('Employee', 'employee');

        UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 50,
        ]);

        $menuItem = $this->createMenuItem('صنف بدل', 120);

        Sanctum::actingAs($cashier);

        $this->postJson('/api/pos/settlement-preview', [
            'scenario' => 'employee_allowance',
            'user_id' => $employee->id,
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 1,
                    'modifiers' => [],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.covered_amount', 50)
            ->assertJsonPath('data.remaining_payable_amount', 70)
            ->assertJsonPath('data.monthly_allowance_remaining', 50);
    }

    public function test_pos_free_meal_preview_only_covers_eligible_items(): void
    {
        $cashier = $this->createCashierWithSettlementPermission();
        $employee = $this->createUserWithRole('Employee', 'employee');
        $eligible = $this->createMenuItem('صنف مؤهل', 80);
        $nonEligible = $this->createMenuItem('صنف غير مؤهل', 40);

        $profile = UserMealBenefitProfile::create([
            'user_id' => $employee->id,
            'is_active' => true,
            'free_meal_enabled' => true,
            'free_meal_type' => UserMealBenefitFreeMealType::Amount,
            'free_meal_monthly_amount' => 50,
        ]);
        $profile->allowedMenuItems()->sync([$eligible->id]);

        Sanctum::actingAs($cashier);

        $this->postJson('/api/pos/settlement-preview', [
            'scenario' => 'employee_free_meal',
            'user_id' => $employee->id,
            'items' => [
                [
                    'menu_item_id' => $eligible->id,
                    'quantity' => 1,
                    'modifiers' => [],
                ],
                [
                    'menu_item_id' => $nonEligible->id,
                    'quantity' => 1,
                    'modifiers' => [],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.eligible_items_total', 80)
            ->assertJsonPath('data.covered_amount', 50)
            ->assertJsonPath('data.remaining_payable_amount', 70)
            ->assertJsonPath('data.can_apply', true);
    }

    public function test_user_without_permission_cannot_access_pos_settlement_preview(): void
    {
        $cashier = $this->createUserWithRole('Cashier', 'cashier');
        $menuItem = $this->createMenuItem('صنف اختبار', 50);

        Sanctum::actingAs($cashier);

        $this->postJson('/api/pos/settlement-preview', [
            'scenario' => 'employee_allowance',
            'user_id' => $cashier->id,
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 1,
                    'modifiers' => [],
                ],
            ],
        ])->assertForbidden();
    }

    private function createCashierWithSettlementPermission(): User
    {
        $cashier = $this->createUserWithRole('Cashier', 'cashier');
        $permission = Permission::firstOrCreate(
            ['name' => 'orders.apply_special_settlement'],
            ['display_name' => 'تطبيق تسويات خاصة', 'group' => 'tests']
        );
        $cashier->roles->first()->permissions()->syncWithoutDetaching([$permission->id]);

        return $cashier->fresh();
    }

    private function createUserWithRole(string $name, string $roleName): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->fresh(['roles.permissions']);
    }

    private function createMenuItem(string $name, float $price): MenuItem
    {
        $category = MenuCategory::firstOrCreate([
            'name' => 'فئة المعاينة',
        ], [
            'is_active' => true,
        ]);

        return MenuItem::create([
            'category_id' => $category->id,
            'name' => $name,
            'type' => 'simple',
            'base_price' => $price,
            'is_available' => true,
            'is_active' => true,
        ]);
    }
}
