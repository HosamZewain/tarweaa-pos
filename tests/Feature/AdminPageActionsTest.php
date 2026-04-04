<?php

namespace Tests\Feature;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\CashierDrawerSession;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPageActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Shift $shift;
    protected CashierDrawerSession $drawer;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();

        $this->shift = Shift::create([
            'shift_number' => 'SHIFT-ACTIONS-001',
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Actions',
            'identifier' => 'POS-ACTIONS-001',
            'is_active' => true,
        ]);

        $this->drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-ACTIONS-001',
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $this->shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->adminUser->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-ACTIONS-001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Delivered,
            'source' => OrderSource::Pos,
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $this->shift->id,
            'drawer_session_id' => $this->drawer->id,
            'pos_device_id' => $device->id,
            'subtotal' => 125,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 125,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => 125,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);
    }

    public function test_reports_pages_show_print_and_excel_actions(): void
    {
        $pages = [
            '/admin/items-report',
            '/admin/production-report',
            '/admin/hr-overview',
        ];

        foreach ($pages as $url) {
            $this->actingAs($this->adminUser)
                ->get($url)
                ->assertSuccessful()
                ->assertSee('طباعة')
                ->assertSee('تصدير Excel');
        }
    }

    public function test_admin_resource_pages_show_print_and_excel_actions(): void
    {
        $pages = [
            '/admin/orders',
            "/admin/orders/{$this->order->id}",
            '/admin/shifts',
            "/admin/shifts/{$this->shift->id}",
            '/admin/drawer-sessions',
            "/admin/drawer-sessions/{$this->drawer->id}",
        ];

        foreach ($pages as $url) {
            $this->actingAs($this->adminUser)
                ->get($url)
                ->assertSuccessful()
                ->assertSee('طباعة')
                ->assertSee('تصدير Excel');
        }
    }
}
