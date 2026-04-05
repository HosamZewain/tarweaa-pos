<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\AdminActivityLog;
use App\Models\CashierDrawerSession;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderAdminStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $cashier;
    private CashierDrawerSession $drawer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();

        $this->cashier = User::factory()->create([
            'name' => 'Cashier Admin Status',
            'is_active' => true,
        ]);

        $this->cashier->roles()->sync([Role::firstWhere('name', 'cashier')->id]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-ORDER-STATUS-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Admin Status',
            'identifier' => 'POS-ADMIN-STATUS-001',
            'is_active' => true,
        ]);

        $this->drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-ADMIN-STATUS-001',
            'cashier_id' => $this->cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->adminUser->id,
            'opening_balance' => 50,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);
    }

    public function test_admin_can_mark_order_ready_from_view_page(): void
    {
        $order = $this->createOrder(
            orderNumber: 'ORD-ADMIN-READY-001',
            status: OrderStatus::Preparing,
            paymentStatus: PaymentStatus::Unpaid,
            paidAmount: 0,
        );

        Livewire::actingAs($this->adminUser)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('markReady');

        $order->refresh();

        $this->assertSame(OrderStatus::Ready->value, $order->status->value);
        $this->assertNotNull($order->ready_at);

        $activity = AdminActivityLog::query()
            ->where('action', 'marked_ready')
            ->where('subject_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->adminUser->id, $activity->actor_user_id);
        $this->assertSame(OrderStatus::Preparing->value, $activity->old_values['status']);
        $this->assertSame(OrderStatus::Ready->value, $activity->new_values['status']);
    }

    public function test_admin_can_mark_paid_ready_order_delivered_from_view_page(): void
    {
        $order = $this->createOrder(
            orderNumber: 'ORD-ADMIN-DELIVERED-001',
            status: OrderStatus::Ready,
            paymentStatus: PaymentStatus::Paid,
            paidAmount: 75,
            readyAt: now()->subMinutes(3),
        );

        Livewire::actingAs($this->adminUser)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('markDelivered');

        $order->refresh();

        $this->assertSame(OrderStatus::Delivered->value, $order->status->value);
        $this->assertNotNull($order->delivered_at);

        $activity = AdminActivityLog::query()
            ->where('action', 'marked_delivered')
            ->where('subject_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->adminUser->id, $activity->actor_user_id);
        $this->assertSame(OrderStatus::Ready->value, $activity->old_values['status']);
        $this->assertSame(OrderStatus::Delivered->value, $activity->new_values['status']);
    }

    public function test_admin_can_mark_order_ready_after_drawer_is_closed(): void
    {
        $this->drawer->update([
            'status' => DrawerSessionStatus::Closed,
            'ended_at' => now(),
            'closed_by' => $this->adminUser->id,
        ]);

        $order = $this->createOrder(
            orderNumber: 'ORD-ADMIN-READY-CLOSED-001',
            status: OrderStatus::Preparing,
            paymentStatus: PaymentStatus::Unpaid,
            paidAmount: 0,
        );

        Livewire::actingAs($this->adminUser)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('markReady');

        $order->refresh();

        $this->assertSame(OrderStatus::Ready->value, $order->status->value);
        $this->assertNotNull($order->ready_at);
    }

    public function test_admin_can_mark_paid_ready_order_delivered_after_drawer_is_closed(): void
    {
        $this->drawer->update([
            'status' => DrawerSessionStatus::Closed,
            'ended_at' => now(),
            'closed_by' => $this->adminUser->id,
        ]);

        $order = $this->createOrder(
            orderNumber: 'ORD-ADMIN-DELIVERED-CLOSED-001',
            status: OrderStatus::Ready,
            paymentStatus: PaymentStatus::Paid,
            paidAmount: 75,
            readyAt: now()->subMinutes(5),
        );

        Livewire::actingAs($this->adminUser)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('markDelivered');

        $order->refresh();

        $this->assertSame(OrderStatus::Delivered->value, $order->status->value);
        $this->assertNotNull($order->delivered_at);
    }

    private function createOrder(
        string $orderNumber,
        OrderStatus $status,
        PaymentStatus $paymentStatus,
        float $paidAmount,
        ?\Illuminate\Support\Carbon $readyAt = null,
    ): \App\Models\Order {
        return \App\Models\Order::create([
            'order_number' => $orderNumber,
            'type' => OrderType::Takeaway,
            'status' => $status,
            'source' => OrderSource::Pos,
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->drawer->shift_id,
            'drawer_session_id' => $this->drawer->id,
            'pos_device_id' => $this->drawer->pos_device_id,
            'subtotal' => 75,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 75,
            'payment_status' => $paymentStatus,
            'paid_amount' => $paidAmount,
            'change_amount' => 0,
            'refund_amount' => 0,
            'confirmed_at' => now()->subMinutes(10),
            'ready_at' => $readyAt,
        ]);
    }
}
