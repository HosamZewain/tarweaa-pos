<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\ShiftStatus;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\PosDevice;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCreationApiSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_rejects_stale_closed_drawer_guard_instead_of_crashing(): void
    {
        [$cashier, $shift, $device] = $this->createCashierContext();

        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-SAFE-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Closed,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $session->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        Sanctum::actingAs($cashier);

        $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
        ])->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'لا توجد جلسة درج مفتوحة لهذا الكاشير. يرجى فتح الدرج أولاً.',
            ]);

        $this->assertDatabaseMissing('cashier_active_sessions', [
            'cashier_id' => $cashier->id,
        ]);
    }

    public function test_order_creation_rejects_malformed_pos_order_type_configuration_instead_of_crashing(): void
    {
        [$cashier, $shift, $device] = $this->createCashierContext();

        $session = CashierDrawerSession::create([
            'session_number' => 'DRW-SAFE-002',
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
            'drawer_session_id' => $session->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        $posOrderTypeId = DB::table('pos_order_types')->insertGetId([
            'name' => 'Legacy Broken Type',
            'type' => 'broken_takeaway',
            'source' => OrderSource::Pos->value,
            'pricing_rule_type' => 'base_price',
            'pricing_rule_value' => 0,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($cashier);

        $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pos_order_type_id' => $posOrderTypeId,
        ])->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'نوع الطلب المحدد غير صالح أو غير نشط.',
            ]);
    }

    /**
     * @return array{0: User, 1: Shift, 2: PosDevice}
     */
    private function createCashierContext(): array
    {
        $cashier = User::factory()->create([
            'name' => 'Safe Cashier',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHF-SAFE-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'Safe POS',
            'identifier' => 'SAFE-POS-01',
            'is_active' => true,
        ]);

        return [$cashier, $shift, $device];
    }
}
