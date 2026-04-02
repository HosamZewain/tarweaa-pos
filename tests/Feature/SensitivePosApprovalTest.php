<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Models\AdminActivityLog;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SensitivePosApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_returns_manager_approvers_for_sensitive_actions(): void
    {
        $cashier = $this->createUserWithRole('Cashier User', 'cashier');
        $manager = $this->createUserWithRole('Manager User', 'manager', pin: '4321');
        $admin = $this->createUserWithRole('Admin User', 'admin', pin: '9876');

        Sanctum::actingAs($cashier->fresh());

        $this->getJson('/api/pos/manager-approvers')
            ->assertOk()
            ->assertJsonFragment(['id' => $manager->id, 'name' => $manager->name])
            ->assertJsonFragment(['id' => $admin->id, 'name' => $admin->name]);
    }

    public function test_cash_in_requires_manager_approval_and_logs_full_details(): void
    {
        $cashier = $this->createUserWithRole('Cashier User', 'cashier');
        $manager = $this->createUserWithRole('Manager User', 'manager', pin: '4321');
        $context = $this->createDrawerContext($cashier);

        Sanctum::actingAs($cashier->fresh());

        $this->postJson("/api/drawers/{$context['drawerSession']->id}/cash-in", [
            'amount' => 50,
            'notes' => 'إضافة فكة',
        ])->assertStatus(422);

        $this->postJson("/api/drawers/{$context['drawerSession']->id}/cash-in", [
            'amount' => 50,
            'notes' => 'إضافة فكة',
            'approver_id' => $manager->id,
            'approver_pin' => '4321',
        ])->assertCreated();

        $this->assertDatabaseHas('cash_movements', [
            'drawer_session_id' => $context['drawerSession']->id,
            'cashier_id' => $cashier->id,
            'performed_by' => $cashier->id,
            'type' => 'cash_in',
            'amount' => '50.00',
            'notes' => 'إضافة فكة',
        ]);

        $log = AdminActivityLog::query()
            ->where('action', 'cash_in_recorded')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($cashier->id, $log->actor_user_id);
        $this->assertSame('drawer_sessions', $log->module);
        $this->assertEquals(50.0, $log->new_values['amount'] ?? null);
        $this->assertSame('إضافة فكة', $log->new_values['notes'] ?? null);
        $this->assertSame($manager->id, $log->meta['approved_by_user_id'] ?? null);
        $this->assertSame($context['drawerSession']->session_number, $log->new_values['session_number'] ?? null);
    }

    public function test_owner_charge_requires_manager_approval_and_logs_details(): void
    {
        $cashier = $this->createUserWithRole(
            'Cashier User',
            'cashier',
            permissions: ['orders.apply_special_settlement'],
        );
        $manager = $this->createUserWithRole('Manager User', 'manager', pin: '4321');
        $owner = $this->createUserWithRole('Owner User', 'owner');
        UserMealBenefitProfile::create([
            'user_id' => $owner->id,
            'is_active' => true,
            'can_receive_owner_charge_orders' => true,
        ]);

        $context = $this->createDrawerContext($cashier);
        $order = $this->createPendingOrder($cashier, $context);

        Sanctum::actingAs($cashier->fresh());

        $this->postJson("/api/orders/{$order->id}/settlement", [
            'scenario' => 'owner_charge',
            'charge_account_user_id' => $owner->id,
            'notes' => 'تحميل على الإدارة',
        ])->assertStatus(422);

        $this->postJson("/api/orders/{$order->id}/settlement", [
            'scenario' => 'owner_charge',
            'charge_account_user_id' => $owner->id,
            'notes' => 'تحميل على الإدارة',
            'approver_id' => $manager->id,
            'approver_pin' => '4321',
        ])->assertOk();

        $order->refresh()->load('settlement.chargeAccountUser');

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->settlement);
        $this->assertSame($owner->id, $order->settlement->chargeAccountUser?->id);

        $log = AdminActivityLog::query()
            ->where('action', 'special_settlement_applied')
            ->where('subject_id', $order->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($cashier->id, $log->actor_user_id);
        $this->assertSame('orders', $log->module);
        $this->assertSame('owner_charge', $log->new_values['scenario'] ?? null);
        $this->assertSame('تحميل على الإدارة', $log->new_values['notes'] ?? null);
        $this->assertSame($manager->id, $log->meta['approved_by_user_id'] ?? null);
        $this->assertSame($owner->name, $log->new_values['charge_account_user'] ?? null);
    }

    private function createDrawerContext(User $cashier): array
    {
        $shift = Shift::create([
            'shift_number' => 'SHF-SENSITIVE-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'Sensitive POS',
            'identifier' => 'POS-SENSITIVE-001',
            'is_active' => true,
        ]);

        $drawerSession = CashierDrawerSession::create([
            'session_number' => 'DRW-SENSITIVE-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now()->subHour(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $drawerSession->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        return compact('shift', 'device', 'drawerSession');
    }

    private function createPendingOrder(User $cashier, array $context): Order
    {
        $category = MenuCategory::create([
            'name' => 'Hot Meals',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Staff Meal',
            'base_price' => 120,
            'cost_price' => 40,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SENSITIVE-0001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $cashier->id,
            'shift_id' => $context['shift']->id,
            'drawer_session_id' => $context['drawerSession']->id,
            'pos_device_id' => $context['device']->id,
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
            'created_by' => $cashier->id,
            'updated_by' => $cashier->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'unit_price' => 120,
            'cost_price' => 40,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => 120,
            'status' => 'pending',
        ]);

        return $order;
    }

    private function createUserWithRole(string $name, string $roleName, array $permissions = [], ?string $pin = null): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'pin' => $pin,
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['display_name' => ucfirst($roleName), 'is_active' => true]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => $permissionName, 'group' => 'tests']
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $user;
    }
}
