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
use App\Models\PosDevice;
use App\Models\Shift;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CounterOrderCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_modify_orders_and_force_updates_only_stale_paid_counter_orders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 10:00:00', BusinessTime::timezone()));

        $context = $this->createOperationalContext();

        $staleReadyOrder = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0001',
            'status' => OrderStatus::Ready,
            'payment_status' => PaymentStatus::Paid,
            'created_at' => BusinessTime::today()->copy()->subDay()->addHours(5)->utc(),
            'confirmed_at' => BusinessTime::today()->copy()->subDay()->addHours(5)->addMinutes(10)->utc(),
            'ready_at' => BusinessTime::today()->copy()->subDay()->addHours(5)->addMinutes(20)->utc(),
        ]);

        $stalePreparingOrder = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0002',
            'status' => OrderStatus::Preparing,
            'payment_status' => PaymentStatus::Paid,
            'created_at' => BusinessTime::today()->copy()->subDay()->addHours(6)->utc(),
            'confirmed_at' => BusinessTime::today()->copy()->subDay()->addHours(6)->addMinutes(5)->utc(),
        ]);

        $todayReadyOrder = $this->createOrder($context, [
            'order_number' => 'ORD-20260329-0001',
            'status' => OrderStatus::Ready,
            'payment_status' => PaymentStatus::Paid,
            'created_at' => BusinessTime::today()->copy()->addHours(1)->utc(),
            'confirmed_at' => BusinessTime::today()->copy()->addHours(1)->addMinutes(5)->utc(),
            'ready_at' => BusinessTime::today()->copy()->addHours(1)->addMinutes(15)->utc(),
        ]);

        $staleUnpaidOrder = $this->createOrder($context, [
            'order_number' => 'ORD-20260328-0003',
            'status' => OrderStatus::Ready,
            'payment_status' => PaymentStatus::Unpaid,
            'created_at' => BusinessTime::today()->copy()->subDay()->addHours(7)->utc(),
        ]);

        $this->artisan('orders:mark-stale-counter-orders-delivered')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Ready, $staleReadyOrder->fresh()->status);
        $this->assertSame(OrderStatus::Preparing, $stalePreparingOrder->fresh()->status);
        $this->assertSame(OrderStatus::Ready, $todayReadyOrder->fresh()->status);
        $this->assertSame(OrderStatus::Ready, $staleUnpaidOrder->fresh()->status);

        $this->artisan('orders:mark-stale-counter-orders-delivered --force')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Delivered, $staleReadyOrder->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $stalePreparingOrder->fresh()->status);
        $this->assertSame(OrderStatus::Ready, $todayReadyOrder->fresh()->status);
        $this->assertSame(OrderStatus::Ready, $staleUnpaidOrder->fresh()->status);
        $this->assertNotNull($staleReadyOrder->fresh()->delivered_at);
        $this->assertNotNull($stalePreparingOrder->fresh()->delivered_at);

        Carbon::setTestNow();
    }

    private function createOperationalContext(): array
    {
        $cashier = User::factory()->create([
            'name' => 'Cleanup Cashier',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHF-CLEANUP-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => BusinessTime::today()->copy()->subDay()->addHours(4)->utc(),
        ]);

        $device = PosDevice::create([
            'name' => 'Cleanup POS',
            'identifier' => 'POS-CLEANUP-001',
            'is_active' => true,
        ]);

        $drawerSession = CashierDrawerSession::create([
            'session_number' => 'DRW-CLEANUP-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => BusinessTime::today()->copy()->subDay()->addHours(4)->utc(),
        ]);

        return compact('cashier', 'shift', 'device', 'drawerSession');
    }

    private function createOrder(array $context, array $attributes = []): Order
    {
        $timestamps = [
            'created_at' => $attributes['created_at'] ?? null,
            'updated_at' => $attributes['updated_at'] ?? null,
        ];

        unset($attributes['created_at'], $attributes['updated_at']);

        $order = Order::create(array_merge([
            'order_number' => 'ORD-TEST-0001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Ready,
            'source' => OrderSource::Pos,
            'cashier_id' => $context['cashier']->id,
            'shift_id' => $context['shift']->id,
            'drawer_session_id' => $context['drawerSession']->id,
            'pos_device_id' => $context['device']->id,
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 50,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => 50,
            'change_amount' => 0,
        ], $attributes));

        $order->forceFill(array_filter($timestamps, fn ($value) => $value !== null))->saveQuietly();

        return $order->fresh();
    }
}
