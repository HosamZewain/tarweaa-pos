<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\PosDevice;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DrawerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashierA;
    private User $cashierB;
    private Shift $shift;
    private PosDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $opener = User::factory()->create([
            'name' => 'Shift Opener',
            'is_active' => true,
        ]);

        $this->cashierA = User::factory()->create([
            'name' => 'Cashier A',
            'is_active' => true,
        ]);

        $this->cashierB = User::factory()->create([
            'name' => 'Cashier B',
            'is_active' => true,
        ]);

        $this->shift = Shift::create([
            'shift_number' => 'SHF-TEST-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $opener->id,
            'started_at' => now(),
        ]);

        $this->device = PosDevice::create([
            'name' => 'POS Test Device',
            'identifier' => 'POS-TEST-001',
            'is_active' => true,
        ]);
    }

    public function test_cashier_cannot_open_drawer_for_another_cashier(): void
    {
        Sanctum::actingAs($this->cashierA);

        $response = $this->postJson('/api/drawers/open', [
            'cashier_id' => $this->cashierB->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opening_balance' => 150,
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'غير مصرح لك بـ: فتح درج لكاشير آخر',
            ]);

        $this->assertDatabaseMissing('cashier_drawer_sessions', [
            'cashier_id' => $this->cashierB->id,
            'shift_id' => $this->shift->id,
        ]);
    }

    public function test_cashier_cannot_close_another_cashiers_drawer(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-001',
            'cashier_id' => $this->cashierB->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierB->id,
            'opening_balance' => 200,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierB->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $response = $this->postJson("/api/drawers/{$session->id}/close", [
            'actual_cash' => 200,
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'غير مصرح لك بـ: إدارة جلسة درج لا تخصك',
            ]);

        $this->assertDatabaseHas('cashier_active_sessions', [
            'cashier_id' => $this->cashierB->id,
            'drawer_session_id' => $session->id,
        ]);

        $this->assertDatabaseHas('cashier_drawer_sessions', [
            'id' => $session->id,
            'status' => DrawerSessionStatus::Open->value,
        ]);
    }
}
