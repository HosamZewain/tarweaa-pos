<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Filament\Widgets\CategorySalesChartWidget;
use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\OrderStatusChartWidget;
use App\Filament\Widgets\OrderVolumeChartWidget;
use App\Filament\Widgets\SalesChartWidget;
use App\Filament\Widgets\TopSellingItemsWidget;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\ReportService;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class DashboardRevenueFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Shift $shift;
    private CashierDrawerSession $drawer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->admin = User::where('email', 'admin@pos.com')->firstOrFail();
        $this->cashier = User::factory()->create([
            'name' => 'Cashier Analytics',
            'username' => 'cashier-analytics',
            'is_active' => true,
        ]);

        $this->cashier->roles()->sync([Role::firstWhere('name', 'cashier')->id]);

        $this->shift = Shift::create([
            'shift_number' => 'SHIFT-REPORTS-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Reports',
            'identifier' => 'POS-REPORTS-1',
            'is_active' => true,
        ]);

        $this->drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-REPORTS-001',
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->admin->id,
            'opening_balance' => 0,
            'status' => 'open',
            'started_at' => now(),
        ]);
    }

    public function test_sales_reports_exclude_unpaid_and_cancelled_orders(): void
    {
        $paidCash = $this->createOrder('PAID-CASH', 100, PaymentStatus::Paid, OrderStatus::Confirmed, PaymentMethod::Cash);
        $paidCard = $this->createOrder('PAID-CARD', 80, PaymentStatus::Paid, OrderStatus::Delivered, PaymentMethod::Card);
        $this->createOrder('UNPAID', 60, PaymentStatus::Unpaid, OrderStatus::Pending, null);
        $this->createOrder('CANCELLED', 40, PaymentStatus::Paid, OrderStatus::Cancelled, PaymentMethod::Cash);

        $report = app(ReportService::class);
        $businessDate = BusinessTime::today()->toDateString();

        $daily = $report->getDailySales($businessDate, $businessDate);
        $items = $report->getSalesByItem($businessDate, $businessDate);
        $categories = $report->getSalesByCategory($businessDate, $businessDate);
        $payments = $report->getSalesByPaymentMethod($businessDate, $businessDate)->keyBy('payment_method');
        $byShift = $report->getSalesByShift($businessDate, $businessDate)->first();
        $byCashier = $report->getSalesByCashier($businessDate, $businessDate)->first();

        $this->assertSame(2, $daily['totals']['total_orders']);
        $this->assertSame(180.0, (float) $daily['totals']['gross_revenue']);
        $this->assertSame(2.0, (float) $items->sum('total_quantity'));
        $this->assertSame(180.0, round((float) $items->sum('net_revenue'), 2));
        $this->assertSame(180.0, round((float) $categories->sum('net_revenue'), 2));
        $this->assertSame(100.0, (float) $payments->get(PaymentMethod::Cash->value)->total_amount);
        $this->assertSame(80.0, (float) $payments->get(PaymentMethod::Card->value)->total_amount);
        $this->assertSame(2, (int) $byShift->total_orders);
        $this->assertSame(180.0, (float) $byShift->gross_revenue);
        $this->assertSame(2, (int) $byCashier->total_orders);
        $this->assertSame(180.0, (float) $byCashier->gross_revenue);

        $this->assertSame('100.00', $paidCash->fresh()->paid_amount);
        $this->assertSame('80.00', $paidCard->fresh()->paid_amount);
    }

    public function test_dashboard_widgets_use_paid_non_cancelled_orders_only(): void
    {
        $this->actingAs($this->admin);

        $category = MenuCategory::firstOrCreate(['name' => 'Dashboard Category'], ['is_active' => true]);

        $this->createOrder('WGT-PAID-CASH', 100, PaymentStatus::Paid, OrderStatus::Confirmed, PaymentMethod::Cash, $category);
        $this->createOrder('WGT-PAID-CARD', 80, PaymentStatus::Paid, OrderStatus::Ready, PaymentMethod::Card, $category);
        $this->createOrder('WGT-UNPAID', 60, PaymentStatus::Unpaid, OrderStatus::Pending, null, $category);
        $this->createOrder('WGT-CANCELLED', 40, PaymentStatus::Paid, OrderStatus::Cancelled, PaymentMethod::Cash, $category);

        $stats = $this->invokeProtectedMethod(app(DashboardStatsWidget::class), 'getStats');
        $salesChart = $this->invokeProtectedMethod(app(SalesChartWidget::class), 'getData');
        $volumeChart = $this->invokeProtectedMethod(app(OrderVolumeChartWidget::class), 'getData');
        $categoryChart = $this->invokeProtectedMethod(app(CategorySalesChartWidget::class), 'getData');
        $statusChart = $this->invokeProtectedMethod(app(OrderStatusChartWidget::class), 'getData');
        $topSelling = app(TopSellingItemsWidget::class)->getViewData();

        $statsByLabel = collect($stats)->keyBy(fn ($stat) => $stat->getLabel());

        $this->assertStringContainsString('180.00', (string) $statsByLabel['مبيعات اليوم']->getValue());
        $this->assertSame('2', trim((string) $statsByLabel['طلبات اليوم']->getValue()));
        $this->assertSame(180.0, (float) collect($salesChart['datasets'][0]['data'])->last());
        $this->assertSame(2, (int) collect($volumeChart['datasets'][0]['data'])->last());
        $this->assertSame(180.0, round(array_sum($categoryChart['datasets'][0]['data']), 2));
        $this->assertSame(2.0, round($topSelling['items']->sum('total_qty'), 2));
        $this->assertSame(180.0, round($topSelling['items']->sum('total_rev'), 2));
        $this->assertSame(2, array_sum($statusChart['datasets'][0]['data']));
    }

    private function createOrder(
        string $suffix,
        float $amount,
        PaymentStatus $paymentStatus,
        OrderStatus $status,
        ?PaymentMethod $paymentMethod,
        ?MenuCategory $category = null,
    ): Order {
        $category ??= MenuCategory::firstOrCreate(['name' => 'Reports Category'], ['is_active' => true]);

        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => "Item {$suffix}",
            'type' => 'simple',
            'base_price' => $amount,
            'cost_price' => 10,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => "ORD-{$suffix}",
            'type' => OrderType::Takeaway,
            'status' => $status,
            'source' => OrderSource::Pos,
            'cashier_id' => $this->cashier->id,
            'shift_id' => $this->shift->id,
            'drawer_session_id' => $this->drawer->id,
            'pos_device_id' => $this->drawer->pos_device_id,
            'subtotal' => $amount,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => $amount,
            'payment_status' => $paymentStatus,
            'paid_amount' => $paymentStatus === PaymentStatus::Paid ? $amount : 0,
            'change_amount' => 0,
            'refund_amount' => 0,
            'confirmed_at' => now(),
            'cancelled_at' => $status === OrderStatus::Cancelled ? now() : null,
            'cancelled_by' => $status === OrderStatus::Cancelled ? $this->admin->id : null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'item_name' => $item->name,
            'unit_price' => $amount,
            'cost_price' => 10,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => $amount,
            'status' => OrderItemStatus::Pending,
        ]);

        if ($paymentMethod) {
            $order->payments()->create([
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'fee_amount' => 0,
                'net_settlement_amount' => $amount,
                'created_by' => $this->cashier->id,
                'updated_by' => $this->cashier->id,
            ]);
        }

        return $order->fresh(['payments', 'settlement']);
    }

    private function invokeProtectedMethod(object $instance, string $method): mixed
    {
        $reflection = new ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($instance);
    }
}
