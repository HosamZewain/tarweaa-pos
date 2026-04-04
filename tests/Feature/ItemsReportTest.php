<?php

namespace Tests\Feature;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
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
use Tests\TestCase;

class ItemsReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $managerUser;
    protected Role $managerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->first()
            ?? User::factory()->create([
                'email' => 'admin@pos.com',
                'is_active' => true,
            ]);

        $adminRole = Role::firstWhere('name', 'admin');
        $this->adminUser->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->managerUser = User::factory()->create([
            'name' => 'Items Manager',
            'email' => 'items.manager@example.com',
            'username' => 'items-manager',
            'is_active' => true,
        ]);

        $this->managerRole = Role::firstWhere('name', 'manager');
        $this->managerUser->roles()->sync([$this->managerRole->id]);
    }

    public function test_report_service_returns_all_items_and_selected_item_statistics(): void
    {
        [$burger, $cola] = $this->seedOrdersForItemsReport();
        $businessDate = BusinessTime::today()->toDateString();

        $report = app(ReportService::class)->getItemsReport(
            $businessDate,
            $businessDate,
            $burger->id,
        );

        $this->assertSame(2, $report['summary']['distinct_items_count']);
        $this->assertSame(6.0, $report['summary']['total_quantity']);
        $this->assertSame(350.0, $report['summary']['gross_revenue']);
        $this->assertSame('برجر', $report['selectedItem']['summary']['item_name']);
        $this->assertSame(3.0, $report['selectedItem']['summary']['total_quantity']);
        $this->assertSame(300.0, $report['selectedItem']['summary']['total_revenue']);
        $this->assertSame(2, $report['selectedItem']['summary']['orders_count']);
        $this->assertCount(2, $report['selectedItem']['variants']);
        $this->assertCount(1, $report['selectedItem']['daily']);
        $this->assertSame('مشروب', $report['items']->firstWhere('menu_item_id', $cola->id)['category_name']);
    }

    public function test_admin_can_view_items_report_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/items-report')
            ->assertSuccessful()
            ->assertSee('تقرير الأصناف');
    }

    public function test_manager_without_permission_cannot_access_items_report_page(): void
    {
        $this->actingAs($this->managerUser)
            ->get('/admin/items-report')
            ->assertForbidden();

        $this->managerRole->givePermissionTo('reports.items.view');

        $this->actingAs($this->managerUser->fresh())
            ->get('/admin/items-report')
            ->assertSuccessful();
    }

    private function seedOrdersForItemsReport(): array
    {
        $shift = Shift::create([
            'shift_number' => 'SHIFT-ITEMS-001',
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Items Report',
            'identifier' => 'POS-ITEMS-001',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-ITEMS-001',
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->adminUser->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now(),
        ]);

        $food = MenuCategory::create(['name' => 'طعام', 'is_active' => true]);
        $drinks = MenuCategory::create(['name' => 'مشروب', 'is_active' => true]);

        $burger = MenuItem::create([
            'category_id' => $food->id,
            'name' => 'برجر',
            'type' => 'simple',
            'base_price' => 100,
            'cost_price' => 40,
            'is_available' => true,
            'is_active' => true,
        ]);

        $cola = MenuItem::create([
            'category_id' => $drinks->id,
            'name' => 'كولا',
            'type' => 'simple',
            'base_price' => 25,
            'cost_price' => 10,
            'is_available' => true,
            'is_active' => true,
        ]);

        $orderOne = $this->createPaidOrder('ORD-ITEMS-001', $shift->id, $drawer->id, $device->id, 225);
        $orderTwo = $this->createPaidOrder('ORD-ITEMS-002', $shift->id, $drawer->id, $device->id, 125);
        $this->createCancelledOrder('ORD-ITEMS-003', $shift->id, $drawer->id, $device->id, 999);

        OrderItem::create([
            'order_id' => $orderOne->id,
            'menu_item_id' => $burger->id,
            'item_name' => 'برجر',
            'variant_name' => 'دبل',
            'unit_price' => 100,
            'cost_price' => 40,
            'quantity' => 2,
            'discount_amount' => 0,
            'total' => 200,
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $orderOne->id,
            'menu_item_id' => $cola->id,
            'item_name' => 'كولا',
            'unit_price' => 25,
            'cost_price' => 10,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => 25,
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $orderTwo->id,
            'menu_item_id' => $burger->id,
            'item_name' => 'برجر',
            'variant_name' => 'سنجل',
            'unit_price' => 100,
            'cost_price' => 40,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => 100,
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $orderTwo->id,
            'menu_item_id' => $cola->id,
            'item_name' => 'كولا',
            'unit_price' => 25,
            'cost_price' => 10,
            'quantity' => 2,
            'discount_amount' => 0,
            'total' => 25,
            'status' => 'pending',
        ]);

        return [$burger, $cola];
    }

    private function createPaidOrder(string $number, int $shiftId, int $drawerId, int $deviceId, float $total): Order
    {
        return Order::create([
            'order_number' => $number,
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Delivered,
            'source' => OrderSource::Pos,
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $shiftId,
            'drawer_session_id' => $drawerId,
            'pos_device_id' => $deviceId,
            'subtotal' => $total,
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
    }

    private function createCancelledOrder(string $number, int $shiftId, int $drawerId, int $deviceId, float $total): Order
    {
        return Order::create([
            'order_number' => $number,
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Cancelled,
            'source' => OrderSource::Pos,
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $shiftId,
            'drawer_session_id' => $drawerId,
            'pos_device_id' => $deviceId,
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => $total,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);
    }
}
