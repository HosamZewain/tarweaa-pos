<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\PosDevice;
use App\Models\Role;
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
    private Role $cashierRole;

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

        $this->cashierRole = Role::create([
            'name' => 'cashier',
            'display_name' => 'Cashier',
            'is_active' => true,
        ]);

        $this->cashierA->roles()->attach($this->cashierRole->id, [
            'assigned_at' => now(),
            'assigned_by' => $opener->id,
        ]);

        $this->cashierB->roles()->attach($this->cashierRole->id, [
            'assigned_at' => now(),
            'assigned_by' => $opener->id,
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

    public function test_cashier_cannot_view_live_financial_summary_for_their_open_session(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-002',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $response = $this->getJson("/api/drawers/{$session->id}/summary");

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'غير مصرح لك بـ: عرض الإحصائيات المالية قبل جرد الدرج',
            ]);
    }

    public function test_cashier_can_preview_close_summary_after_entering_actual_cash(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-003',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $response = $this->postJson("/api/drawers/{$session->id}/close-preview", [
            'actual_cash' => 100,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.actual_cash', 100)
            ->assertJsonStructure([
                'data' => [
                    'session_number',
                    'expected_cash',
                    'actual_cash',
                    'variance',
                    'matches_expected',
                    'preview_token',
                ],
            ]);
    }

    public function test_active_drawer_exposes_reconciliation_lock_after_preview(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-003A',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $this->postJson("/api/drawers/{$session->id}/close-preview", [
            'actual_cash' => 90,
        ])->assertOk();

        $response = $this->getJson('/api/drawers/active');

        $response
            ->assertOk()
            ->assertJsonPath('data.close_reconciliation.locked', true)
            ->assertJsonPath('data.close_reconciliation.actual_cash', 90)
            ->assertJsonPath('data.close_reconciliation.matches_expected', false);
    }

    public function test_cashier_cannot_change_declared_amount_after_preview_started(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-003B',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $firstPreview = $this->postJson("/api/drawers/{$session->id}/close-preview", [
            'actual_cash' => 90,
        ]);

        $secondPreview = $this->postJson("/api/drawers/{$session->id}/close-preview", [
            'actual_cash' => 100,
        ]);

        $secondPreview
            ->assertOk()
            ->assertJsonPath('data.actual_cash', 90)
            ->assertJsonPath('data.preview_token', $firstPreview->json('data.preview_token'));
    }

    public function test_cashier_cannot_close_without_review_token(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-004',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $response = $this->postJson("/api/drawers/{$session->id}/close", [
            'actual_cash' => 100,
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'يجب مراجعة الجرد وتأكيد المبلغ المُعلن قبل إغلاق الدرج.',
            ]);
    }

    public function test_cashier_can_close_only_with_matching_review_token(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-005',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 0,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $previewResponse = $this->postJson("/api/drawers/{$session->id}/close-preview", [
            'actual_cash' => 0,
        ]);

        $token = $previewResponse->json('data.preview_token');

        $closeResponse = $this->postJson("/api/drawers/{$session->id}/close", [
            'actual_cash' => 0,
            'preview_token' => $token,
        ]);

        $closeResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cashier_drawer_sessions', [
            'id' => $session->id,
            'status' => DrawerSessionStatus::Closed->value,
            'closing_balance' => 0.00,
        ]);
    }

    public function test_cashier_cannot_close_with_variance_without_reason(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-006',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $previewResponse = $this->postJson("/api/drawers/{$session->id}/close-preview", [
            'actual_cash' => 90,
        ]);

        $previewResponse
            ->assertOk()
            ->assertJsonPath('data.can_close', true)
            ->assertJsonPath('data.variance', -10);

        $token = $previewResponse->json('data.preview_token');

        $closeResponse = $this->postJson("/api/drawers/{$session->id}/close", [
            'actual_cash' => 90,
            'preview_token' => $token,
        ]);

        $closeResponse
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'سبب الفرق مطلوب عند وجود عجز أو فائض قبل إغلاق الدرج.',
            ]);

        $this->assertDatabaseHas('cashier_drawer_sessions', [
            'id' => $session->id,
            'status' => DrawerSessionStatus::Open->value,
        ]);
    }

    public function test_cashier_can_close_with_variance_and_reason_is_recorded(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-006A',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $previewResponse = $this->postJson("/api/drawers/{$session->id}/close-preview", [
            'actual_cash' => 90,
        ]);

        $token = $previewResponse->json('data.preview_token');

        $closeResponse = $this->postJson("/api/drawers/{$session->id}/close", [
            'actual_cash' => 90,
            'preview_token' => $token,
            'notes' => 'نقص في العد بعد الجرد النهائي',
        ]);

        $closeResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cashier_drawer_sessions', [
            'id' => $session->id,
            'status' => DrawerSessionStatus::Closed->value,
            'closing_balance' => 90.00,
            'expected_balance' => 100.00,
            'cash_difference' => -10.00,
            'notes' => 'نقص في العد بعد الجرد النهائي',
        ]);
    }

    public function test_cashier_cannot_record_cash_movement_once_reconciliation_has_started(): void
    {
        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-TEST-007',
            'cashier_id' => $this->cashierA->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $this->device->id,
            'opened_by' => $this->cashierA->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashierA->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $this->device->id,
            'shift_id' => $this->shift->id,
        ]);

        Sanctum::actingAs($this->cashierA);

        $this->postJson("/api/drawers/{$session->id}/close-preview", [
            'actual_cash' => 100,
        ])->assertOk();

        $manager = User::factory()->create([
            'name' => 'Manager Approver',
            'pin' => '4321',
            'is_active' => true,
        ]);

        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
        ], [
            'display_name' => 'Manager',
            'is_active' => true,
        ]);

        $manager->roles()->syncWithoutDetaching([$managerRole->id]);

        $response = $this->postJson("/api/drawers/{$session->id}/cash-in", [
            'amount' => 20,
            'notes' => 'Test top-up',
            'approver_id' => $manager->id,
            'approver_pin' => '4321',
        ]);

        $response
            ->assertStatus(423)
            ->assertJson([
                'success' => false,
                'message' => 'تم بدء جرد إغلاق الدرج بالفعل. يجب إكمال الإغلاق أولاً قبل العودة إلى نقطة البيع.',
            ]);
    }
}
