<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscountApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_apply_discount_only_after_manager_pin_approval(): void
    {
        [$cashier, $manager, $order] = $this->createDiscountOrderContext();

        Sanctum::actingAs($cashier);

        $approvalResponse = $this->postJson('/api/pos/discount-approval', [
            'type' => 'fixed',
            'value' => 10,
            'reason' => 'اعتماد خصم رضا عميل',
            'approver_id' => $manager->id,
            'approver_pin' => '4321',
        ]);

        $approvalResponse
            ->assertOk()
            ->assertJsonPath('data.approver.id', $manager->id);

        $token = $approvalResponse->json('data.approval_token');

        $discountResponse = $this->postJson("/api/orders/{$order->id}/discount", [
            'type' => 'fixed',
            'value' => 10,
            'reason' => 'اعتماد خصم رضا عميل',
            'approval_token' => $token,
        ]);

        $discountResponse
            ->assertOk()
            ->assertJsonPath('data.discount_type', 'fixed')
            ->assertJsonPath('data.discount_amount', '10.00');

        $this->assertDatabaseHas('discount_logs', [
            'order_id' => $order->id,
            'applied_by' => $manager->id,
            'requested_by' => $cashier->id,
            'scope' => 'order',
            'action' => 'applied',
            'discount_type' => 'fixed',
            'discount_amount' => '10.00',
            'reason' => 'اعتماد خصم رضا عميل',
        ]);
    }

    public function test_discount_approval_fails_with_invalid_manager_pin(): void
    {
        [$cashier, $manager] = $this->createDiscountOrderContext();

        Sanctum::actingAs($cashier);

        $response = $this->postJson('/api/pos/discount-approval', [
            'type' => 'percentage',
            'value' => 5,
            'reason' => 'محاولة خصم غير صحيحة',
            'approver_id' => $manager->id,
            'approver_pin' => '9999',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'رمز اعتماد المدير غير صحيح.',
            ]);
    }

    private function createDiscountOrderContext(): array
    {
        $permission = Permission::create([
            'name' => 'apply_discount',
            'display_name' => 'تطبيق الخصم',
            'group' => 'العمليات',
        ]);

        $cashierRole = Role::create([
            'name' => 'cashier',
            'display_name' => 'Cashier',
            'is_active' => true,
        ]);

        $cashierRole->permissions()->attach($permission->id);

        $managerRole = Role::create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create([
            'name' => 'Cashier User',
            'is_active' => true,
            'pin' => '1234',
        ]);
        $cashier->roles()->attach($cashierRole->id, [
            'assigned_at' => now(),
            'assigned_by' => $cashier->id,
        ]);

        $manager = User::factory()->create([
            'name' => 'Manager User',
            'is_active' => true,
            'pin' => '4321',
        ]);
        $manager->roles()->attach($managerRole->id, [
            'assigned_at' => now(),
            'assigned_by' => $cashier->id,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-DISC-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Discount',
            'identifier' => 'POS-DISC-001',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-DISC-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        $category = MenuCategory::create([
            'name' => 'خصومات',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'ساندوتش',
            'type' => 'simple',
            'base_price' => 100,
            'cost_price' => 40,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-DISC-APP-001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
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

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'ساندوتش',
            'unit_price' => 100,
            'cost_price' => 40,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => 100,
            'status' => OrderItemStatus::Pending,
        ]);

        return [$cashier->fresh(), $manager->fresh(), $order];
    }
}
