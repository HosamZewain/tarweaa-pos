<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\Permission;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosDiscountAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Shift $shift;
    private PosDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create([
            'name' => 'Cashier POS',
            'is_active' => true,
        ]);

        $this->shift = Shift::create([
            'shift_number' => 'SHF-POS-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $this->cashier->id,
            'started_at' => now(),
        ]);

        $this->device = PosDevice::create([
            'name' => 'POS Main',
            'identifier' => 'POS-MAIN-001',
            'is_active' => true,
        ]);

        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-POS-001',
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashier->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);
    }

    public function test_cashier_without_discount_permission_cannot_create_discounted_order(): void
    {
        Sanctum::actingAs($this->cashier);

        $response = $this->postJson('/api/orders', [
            'type' => 'takeaway',
            'source' => 'pos',
            'discount_type' => 'fixed',
            'discount_value' => 10,
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'ليس لديك صلاحية لتطبيق الخصم على الطلب.',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cashier_with_discount_permission_can_create_discounted_order(): void
    {
        $permission = Permission::create([
            'name' => 'apply_discount',
            'display_name' => 'تطبيق الخصم',
            'group' => 'العمليات',
        ]);

        $role = Role::create([
            'name' => 'discount_cashier',
            'display_name' => 'كاشير بخصم',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission->id);
        $this->cashier->roles()->attach($role->id, [
            'assigned_at' => now(),
            'assigned_by' => $this->cashier->id,
        ]);

        Sanctum::actingAs($this->cashier->fresh());

        $response = $this->postJson('/api/orders', [
            'type' => 'takeaway',
            'source' => 'pos',
            'discount_type' => 'fixed',
            'discount_value' => 10,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.discount_type', 'fixed')
            ->assertJsonPath('data.discount_value', '10.00');

        $this->assertDatabaseHas('orders', [
            'cashier_id' => $this->cashier->id,
            'discount_type' => 'fixed',
        ]);
    }
}
