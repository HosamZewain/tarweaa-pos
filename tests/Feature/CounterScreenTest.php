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

class CounterScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_load_counter_shell_and_login_redirect_target(): void
    {
        $this->get('/counter')
            ->assertSuccessful()
            ->assertSee('شاشة التسليم والاستلام')
            ->assertSee('شاشة كل الطلبات')
            ->assertSee('رقم الطلب')
            ->assertSee('اضغط Enter للتسليم')
            ->assertSee('فاتح');

        $this->get('/counter/odd')
            ->assertSuccessful()
            ->assertSee('شاشة التسليم والاستلام')
            ->assertSee('شاشة الفردية')
            ->assertSee('رقم الطلب')
            ->assertSee('اضغط Enter للتسليم')
            ->assertSee('فاتح');

        $this->get('/counter-screen/odd')
            ->assertRedirect('/counter/odd');

        $this->get('/pos/login?redirect=%2Fcounter%2Fodd')
            ->assertSuccessful();
    }

    public function test_paid_orders_appear_in_counter_flow_and_are_split_by_odd_even(): void
    {
        $counterUser = $this->createUserWithPermissions('Counter User', ['view_counter_screen', 'handover_counter_orders']);
        $context = $this->createOperationalContext();

        $oddReady = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0001',
            'status' => OrderStatus::Ready,
            'payment_status' => PaymentStatus::Paid,
            'confirmed_at' => now()->subMinutes(8),
            'ready_at' => now()->subMinutes(2),
        ]);

        $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0002',
            'status' => OrderStatus::Preparing,
            'payment_status' => PaymentStatus::Paid,
            'confirmed_at' => now()->subMinutes(6),
        ]);

        $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0003',
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'confirmed_at' => now()->subMinutes(10),
            'delivered_at' => now()->subMinute(),
        ]);

        $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0005',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        Sanctum::actingAs($counterUser->fresh());

        $oddResponse = $this->getJson('/api/counter/orders/odd')
            ->assertOk()
            ->json('data');

        $this->assertSame('odd', $oddResponse['lane']);
        $this->assertCount(1, $oddResponse['orders']);
        $this->assertSame($oddReady->id, $oddResponse['orders'][0]['id']);
        $this->assertSame('ready', $oddResponse['orders'][0]['status']);
        $this->assertSame(1, $oddResponse['stats']['ready']);

        $evenResponse = $this->getJson('/api/counter/orders/even')
            ->assertOk()
            ->json('data');

        $this->assertSame('even', $evenResponse['lane']);
        $this->assertCount(1, $evenResponse['orders']);
        $this->assertSame('preparing', $evenResponse['orders'][0]['status']);

        $allResponse = $this->getJson('/api/counter/orders/all')
            ->assertOk()
            ->json('data');

        $this->assertSame('all', $allResponse['lane']);
        $this->assertCount(2, $allResponse['orders']);
        $this->assertSame($oddReady->id, $allResponse['orders'][0]['id']);
    }

    public function test_kitchen_ready_transition_is_reflected_in_counter_flow(): void
    {
        $counterUser = $this->createUserWithPermissions('Counter User', ['view_counter_screen', 'handover_counter_orders']);
        $kitchenUser = $this->createUserWithPermissions('Kitchen User', ['view_kitchen', 'mark_order_ready']);
        $context = $this->createOperationalContext();

        $order = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0007',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'confirmed_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($kitchenUser->fresh());
        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => OrderStatus::Preparing->value,
        ])->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => OrderStatus::Ready->value,
        ])->assertOk();

        Sanctum::actingAs($counterUser->fresh());
        $response = $this->getJson('/api/counter/orders/odd')
            ->assertOk()
            ->json('data.orders');

        $this->assertCount(1, $response);
        $this->assertSame($order->id, $response[0]['id']);
        $this->assertSame('ready', $response[0]['status']);
    }

    public function test_counter_orders_are_sorted_oldest_first(): void
    {
        $counterUser = $this->createUserWithPermissions('Counter User', ['view_counter_screen', 'handover_counter_orders']);
        $context = $this->createOperationalContext();

        $olderOrder = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0013',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'created_at' => now()->subMinutes(12),
            'confirmed_at' => now()->subMinutes(11),
        ]);

        $newerOrder = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0015',
            'status' => OrderStatus::Ready,
            'payment_status' => PaymentStatus::Paid,
            'created_at' => now()->subMinutes(4),
            'confirmed_at' => now()->subMinutes(3),
            'ready_at' => now()->subMinutes(2),
        ]);

        Sanctum::actingAs($counterUser->fresh());

        $orders = $this->getJson('/api/counter/orders/odd')
            ->assertOk()
            ->json('data.orders');

        $this->assertCount(2, $orders);
        $this->assertSame($olderOrder->id, $orders[0]['id']);
        $this->assertSame($newerOrder->id, $orders[1]['id']);
    }

    public function test_counter_staff_can_mark_ready_order_as_handed_over_and_it_disappears(): void
    {
        $counterUser = $this->createUserWithPermissions('Counter User', ['view_counter_screen', 'handover_counter_orders']);
        $context = $this->createOperationalContext();

        $order = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0009',
            'status' => OrderStatus::Ready,
            'payment_status' => PaymentStatus::Paid,
            'confirmed_at' => now()->subMinutes(12),
            'ready_at' => now()->subMinutes(4),
        ]);

        Sanctum::actingAs($counterUser->fresh());

        $this->postJson("/api/counter/orders/{$order->id}/handover")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Delivered->value);

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at);

        $this->getJson('/api/counter/orders/odd')
            ->assertOk()
            ->assertJsonCount(0, 'data.orders');
    }

    public function test_unauthorized_users_cannot_view_counter_or_trigger_handover(): void
    {
        $cashier = $this->createUserWithPermissions('Cashier User', []);
        $kitchenUser = $this->createUserWithPermissions('Kitchen User', ['view_kitchen', 'mark_order_ready']);
        $context = $this->createOperationalContext();

        $order = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0011',
            'status' => OrderStatus::Ready,
            'payment_status' => PaymentStatus::Paid,
            'confirmed_at' => now()->subMinutes(3),
            'ready_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($cashier->fresh());
        $this->getJson('/api/counter/orders/odd')->assertForbidden();
        $this->postJson("/api/counter/orders/{$order->id}/handover")->assertForbidden();

        Sanctum::actingAs($kitchenUser->fresh());
        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => OrderStatus::Delivered->value,
        ])->assertForbidden();
    }

    private function createOperationalContext(): array
    {
        $cashier = User::factory()->create([
            'name' => 'Order Cashier',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHF-COUNTER-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'Main POS',
            'identifier' => 'POS-COUNTER-001',
            'is_active' => true,
        ]);

        $drawerSession = CashierDrawerSession::create([
            'session_number' => 'DRW-COUNTER-001',
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

    private function createOrder(array $context, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-20260328-0001',
            'type' => OrderType::Pickup,
            'status' => OrderStatus::Confirmed,
            'source' => OrderSource::Pos,
            'cashier_id' => $context['cashier']->id,
            'shift_id' => $context['shift']->id,
            'drawer_session_id' => $context['drawerSession']->id,
            'pos_device_id' => $context['device']->id,
            'customer_name' => 'عميل تجريبي',
            'subtotal' => 50,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 50,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => 50,
            'change_amount' => 0,
            'refund_amount' => 0,
            'confirmed_at' => now()->subMinutes(5),
            'created_by' => $context['cashier']->id,
            'updated_by' => $context['cashier']->id,
        ], $overrides));
    }

    private function createUserWithPermissions(string $name, array $permissions): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);

        if (empty($permissions)) {
            return $user;
        }

        $role = Role::create([
            'name' => strtolower(str_replace(' ', '_', $name)) . '_role',
            'display_name' => $name,
        ]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                [
                    'display_name' => $permissionName,
                    'group' => 'Testing',
                ],
            );

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role->id);

        return $user;
    }
}
