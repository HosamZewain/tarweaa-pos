<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierDrawerSession;
use App\Models\Permission;
use App\Models\PosDevice;
use App\Models\Order;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalSurfaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_user_cannot_access_pos_api_or_open_drawer(): void
    {
        $user = User::factory()->create([
            'name' => 'Kitchen User',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'view_kitchen',
            'display_name' => 'عرض شاشة المطبخ',
            'group' => 'المطبخ',
        ]);

        $role = Role::create([
            'name' => 'kitchen',
            'display_name' => 'Kitchen',
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $shift = Shift::create([
            'shift_number' => 'SHF-KITCHEN-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $user->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'Kitchen POS',
            'identifier' => 'POS-KITCHEN-001',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/pos/menu')
            ->assertForbidden();

        $this->postJson('/api/drawers/open', [
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opening_balance' => 50,
        ])->assertForbidden();
    }

    public function test_user_without_mark_order_ready_permission_cannot_move_order_to_kitchen_statuses(): void
    {
        $kitchenViewer = User::factory()->create([
            'name' => 'Kitchen Viewer',
            'is_active' => true,
        ]);

        $viewerPermission = Permission::create([
            'name' => 'view_kitchen',
            'display_name' => 'عرض شاشة المطبخ',
            'group' => 'المطبخ',
        ]);

        $viewerRole = Role::create([
            'name' => 'kitchen_viewer',
            'display_name' => 'Kitchen Viewer',
        ]);

        $viewerRole->permissions()->attach($viewerPermission->id);
        $kitchenViewer->roles()->attach($viewerRole->id);

        $cashier = User::factory()->create([
            'name' => 'Cashier User',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHF-TRANSITION-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Transition',
            'identifier' => 'POS-TRANSITION-001',
            'is_active' => true,
        ]);

        $drawerSession = CashierDrawerSession::create([
            'session_number' => 'DRW-TRANSITION-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TRANSITION-001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Confirmed,
            'source' => OrderSource::Pos,
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawerSession->id,
            'pos_device_id' => $device->id,
            'subtotal' => 100,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 100,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        Sanctum::actingAs($kitchenViewer->fresh());

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => OrderStatus::Preparing->value,
        ])->assertForbidden();
    }
}
