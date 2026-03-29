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
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessTimezoneOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Shift $shift;
    protected CashierDrawerSession $drawer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->cashier = User::factory()->create([
            'name' => 'Timezone Cashier',
            'username' => 'timezone-cashier',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstWhere('name', 'cashier');
        $this->cashier->roles()->sync([$cashierRole->id]);

        $this->shift = Shift::create([
            'shift_number' => 'SHIFT-TZ-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $this->cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS TZ',
            'identifier' => 'POS-TZ-1',
            'is_active' => true,
        ]);

        $this->drawer = CashierDrawerSession::create([
            'session_number' => 'DRAWER-TZ-001',
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->cashier->id,
            'opening_balance' => 0,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);
    }

    public function test_order_number_and_today_scope_follow_cairo_business_day_boundary(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 29, 22, 30, 0, 'UTC'));

        $previousLocalDayOrder = $this->createOrderAt('ORD-TZ-OLD', '2026-03-29 21:30:00');
        $currentLocalDayOrder = $this->createOrderAt('ORD-TZ-CUR', '2026-03-29 22:10:00');

        $generated = Order::generateOrderNumber();

        $this->assertStringStartsWith('ORD-20260330-', $generated);
        $this->assertStringEndsWith('-0002', $generated);
        $this->assertTrue(Order::today()->whereKey($currentLocalDayOrder->id)->exists());
        $this->assertFalse(Order::today()->whereKey($previousLocalDayOrder->id)->exists());
    }

    public function test_daily_sales_report_groups_orders_by_cairo_local_day(): void
    {
        $this->createOrderAt('ORD-TZ-1', '2026-03-29 21:30:00', 50);
        $this->createOrderAt('ORD-TZ-2', '2026-03-29 22:10:00', 80);
        $this->createOrderAt('ORD-TZ-3', '2026-03-30 00:15:00', 40);

        $report = app(ReportService::class)->getDailySales('2026-03-29', '2026-03-30');

        $daily = $report['daily']->keyBy('date');

        $this->assertSame(50.0, (float) $daily['2026-03-29']->gross_revenue);
        $this->assertSame(120.0, (float) $daily['2026-03-30']->gross_revenue);
        $this->assertSame(3, (int) $report['totals']['total_orders']);
        $this->assertSame(170.0, (float) $report['totals']['gross_revenue']);
    }

    protected function createOrderAt(string $orderNumber, string $createdAtUtc, float $total = 100): Order
    {
        $order = Order::create([
            'order_number' => $orderNumber,
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Delivered,
            'source' => OrderSource::Pos,
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
            'drawer_session_id' => $this->drawer->id,
            'pos_device_id' => $this->drawer->pos_device_id,
            'subtotal' => $total,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => $total,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => $total,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        DB::table('orders')
            ->where('id', $order->id)
            ->update([
                'created_at' => $createdAtUtc,
                'updated_at' => $createdAtUtc,
            ]);

        return $order->fresh();
    }
}
