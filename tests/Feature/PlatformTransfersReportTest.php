<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierDrawerSession;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTransfersReportTest extends TestCase
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
            'name' => 'Branch Manager',
            'email' => 'platform.manager@example.com',
            'username' => 'platform-manager',
            'is_active' => true,
        ]);

        $this->managerRole = Role::firstWhere('name', 'manager');
        $this->managerUser->roles()->sync([$this->managerRole->id]);
    }

    public function test_report_service_returns_only_selected_platform_transfer_methods(): void
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier One',
            'username' => 'cashier-one',
            'is_active' => true,
        ]);

        $this->createOrderPayment(
            cashier: $cashier,
            suffix: 'TAL',
            source: OrderSource::Talabat,
            method: PaymentMethod::TalabatPay,
            amount: 150,
            reference: 'T-1001',
        );

        $this->createOrderPayment(
            cashier: $cashier,
            suffix: 'INS',
            source: OrderSource::Pos,
            method: PaymentMethod::InstaPay,
            amount: 90,
            reference: '01012345678',
        );

        $this->createOrderPayment(
            cashier: $cashier,
            suffix: 'CSH',
            source: OrderSource::Pos,
            method: PaymentMethod::Cash,
            amount: 60,
        );

        $report = app(ReportService::class)->getPlatformTransfersReport(
            methods: [
                PaymentMethod::TalabatPay->value,
                PaymentMethod::InstaPay->value,
            ],
        );

        $this->assertCount(2, $report['entries']);
        $this->assertSame(240.0, $report['summary']['total_amount']);
        $this->assertSame(150.0, $report['summary']['platform_amount']);
        $this->assertSame(90.0, $report['summary']['instapay_amount']);
        $this->assertEqualsCanonicalizing(
            [PaymentMethod::TalabatPay->value, PaymentMethod::InstaPay->value],
            $report['entries']->pluck('payment_method')->all(),
        );
    }

    public function test_admin_can_view_platform_transfers_report_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/platform-transfers-report')
            ->assertSuccessful()
            ->assertSee('تقرير تحويلات المنصات');
    }

    public function test_manager_without_permission_cannot_access_platform_transfers_report_page(): void
    {
        $this->actingAs($this->managerUser)
            ->get('/admin/platform-transfers-report')
            ->assertForbidden();

        $this->managerRole->givePermissionTo('reports.platform_transfers.view');

        $this->actingAs($this->managerUser->fresh())
            ->get('/admin/platform-transfers-report')
            ->assertSuccessful();
    }

    protected function createOrderPayment(
        User $cashier,
        string $suffix,
        OrderSource $source,
        PaymentMethod $method,
        float $amount,
        ?string $reference = null,
    ): void {
        $shift = Shift::query()->create([
            'shift_number' => "SHIFT-PLT-{$suffix}",
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::query()->create([
            'name' => "POS {$suffix}",
            'identifier' => "POS-PLT-{$suffix}",
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::query()->create([
            'session_number' => "DRAWER-PLT-{$suffix}",
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 0,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        $order = Order::query()->create([
            'order_number' => "ORD-PLT-{$suffix}",
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Delivered,
            'source' => $source,
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'subtotal' => $amount,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => $amount,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => $amount,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        OrderPayment::query()->create([
            'order_id' => $order->id,
            'payment_method' => $method,
            'amount' => $amount,
            'reference_number' => $reference,
        ]);
    }
}
