<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierDrawerSession;
use App\Models\Order;
use App\Models\Permission;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MealBenefitApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_special_settlement_permission_cannot_apply_order_settlement(): void
    {
        $cashier = $this->createUserWithPermissions('Cashier', [], 'cashier');
        $admin = $this->createUserWithPermissions('Admin', [], 'admin');
        $context = $this->createOperationalContext($cashier);
        $order = $this->createOrder($context);

        Sanctum::actingAs($cashier->fresh());

        $this->postJson("/api/orders/{$order->id}/settlement", [
            'scenario' => 'owner_charge',
            'charge_account_user_id' => $admin->id,
        ])->assertForbidden();
    }

    public function test_user_without_meal_benefit_report_permission_cannot_view_summary(): void
    {
        $cashier = $this->createUserWithPermissions('Cashier', [], 'cashier');
        $employee = $this->createUserWithPermissions('Employee', [], 'employee');

        Sanctum::actingAs($cashier->fresh());

        $this->getJson("/api/meal-benefits/users/{$employee->id}/summary")
            ->assertForbidden();
    }

    private function createOperationalContext(User $cashier): array
    {
        $shift = Shift::create([
            'shift_number' => 'SHF-MEAL-AUTH-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'Meal Benefit POS',
            'identifier' => 'POS-MEAL-AUTH-001',
            'is_active' => true,
        ]);

        $drawerSession = CashierDrawerSession::create([
            'session_number' => 'DRW-MEAL-AUTH-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now()->subHour(),
        ]);

        return compact('cashier', 'shift', 'device', 'drawerSession');
    }

    private function createOrder(array $context): Order
    {
        return Order::create([
            'order_number' => 'ORD-MEAL-AUTH-0001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $context['cashier']->id,
            'shift_id' => $context['shift']->id,
            'drawer_session_id' => $context['drawerSession']->id,
            'pos_device_id' => $context['device']->id,
            'subtotal' => 50,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 50,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
            'created_by' => $context['cashier']->id,
            'updated_by' => $context['cashier']->id,
        ]);
    }

    private function createUserWithPermissions(string $name, array $permissions, string $roleName): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => $permissionName, 'group_name' => 'tests']
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $user;
    }
}
