<?php

namespace Tests\Feature;

use App\DTOs\ProcessPaymentData;
use App\Enums\DrawerSessionStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Filament\Resources\DrawerSessionResource\Pages\ViewDrawerSession;
use App\Filament\Resources\ShiftResource\Pages\ViewShift;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\CashManagementService;
use App\Services\DrawerSessionService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class NonCashSessionReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_drawer_summary_and_shift_summary_include_talabat_and_instapay_as_non_cash(): void
    {
        [$cashier, $admin, $shift, $drawer] = $this->createSessionContext();

        $cashOrder = $this->createOrder($cashier, $shift, $drawer, 'CASH', OrderSource::Pos, 50);
        $talabatOrder = $this->createOrder($cashier, $shift, $drawer, 'TALABAT', OrderSource::Talabat, 80);
        $instapayOrder = $this->createOrder($cashier, $shift, $drawer, 'INSTA', OrderSource::Pos, 60);

        $paymentService = app(OrderPaymentService::class);

        $paymentService->processPayment(
            order: $cashOrder,
            payments: [new ProcessPaymentData(method: PaymentMethod::Cash, amount: 50)],
            actorId: $cashier->id,
        );

        $paymentService->processPayment(
            order: $talabatOrder,
            payments: [new ProcessPaymentData(method: PaymentMethod::TalabatPay, amount: 80, referenceNumber: 'T-123')],
            actorId: $cashier->id,
        );

        $paymentService->processPayment(
            order: $instapayOrder,
            payments: [new ProcessPaymentData(method: PaymentMethod::InstaPay, amount: 60, referenceNumber: '01001234567')],
            actorId: $cashier->id,
        );

        $drawerSummary = app(DrawerSessionService::class)->getSessionSummary($drawer->fresh(), $admin->fresh());
        $shiftSummary = app(CashManagementService::class)->getShiftSummary($shift->fresh());
        $closePreview = app(DrawerSessionService::class)->getClosePreview($drawer->fresh(), $cashier->fresh(), 50);

        $this->assertSame(50.0, round((float) $drawerSummary['cash_sales'], 2));
        $this->assertSame(140.0, round((float) $drawerSummary['non_cash_sales'], 2));
        $this->assertSame(50.0, round((float) $drawerSummary['expected_cash'], 2));
        $this->assertSame(50.0, round((float) $closePreview['expected_cash'], 2));
        $this->assertSame(140.0, round((float) $closePreview['non_cash_sales'], 2));
        $this->assertTrue((bool) $closePreview['matches_expected']);

        $this->assertSame(50.0, round((float) ($shiftSummary['payment_breakdown'][PaymentMethod::Cash->value] ?? 0), 2));
        $this->assertSame(80.0, round((float) ($shiftSummary['payment_breakdown'][PaymentMethod::TalabatPay->value] ?? 0), 2));
        $this->assertSame(60.0, round((float) ($shiftSummary['payment_breakdown'][PaymentMethod::InstaPay->value] ?? 0), 2));
        $this->assertSame(50.0, round((float) $shiftSummary['total_expected_cash'], 2));

        Sanctum::actingAs($admin);

        $this->getJson("/api/drawers/{$drawer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.cash_sales', 50)
            ->assertJsonPath('data.non_cash_sales', 140)
            ->assertJsonPath('data.expected_cash', 50);

    }

    public function test_cancelled_orders_are_excluded_from_sales_totals_in_drawer_and_shift_reporting(): void
    {
        [$cashier, $admin, $shift, $drawer] = $this->createSessionContext();

        $cashOrder = $this->createOrder($cashier, $shift, $drawer, 'ACTIVE-CASH', OrderSource::Pos, 50);
        $talabatOrder = $this->createOrder($cashier, $shift, $drawer, 'ACTIVE-TALABAT', OrderSource::Talabat, 80);
        $cancelledTalabatOrder = $this->createOrder($cashier, $shift, $drawer, 'CANCELLED-TALABAT', OrderSource::Talabat, 25);

        $paymentService = app(OrderPaymentService::class);

        $paymentService->processPayment(
            order: $cashOrder,
            payments: [new ProcessPaymentData(method: PaymentMethod::Cash, amount: 50)],
            actorId: $cashier->id,
        );

        $paymentService->processPayment(
            order: $talabatOrder,
            payments: [new ProcessPaymentData(method: PaymentMethod::TalabatPay, amount: 80, referenceNumber: 'T-OK')],
            actorId: $cashier->id,
        );

        $paymentService->processPayment(
            order: $cancelledTalabatOrder,
            payments: [new ProcessPaymentData(method: PaymentMethod::TalabatPay, amount: 25, referenceNumber: 'T-CANCELLED')],
            actorId: $cashier->id,
        );

        $cancelledTalabatOrder->update([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
        ]);

        $drawerSummary = app(DrawerSessionService::class)->getSessionSummary($drawer->fresh(), $admin->fresh());
        $shiftSummary = app(CashManagementService::class)->getShiftSummary($shift->fresh());

        $this->assertSame(2, $drawerSummary['order_count']);
        $this->assertSame(2, $drawerSummary['paid_orders_count']);
        $this->assertSame(80.0, round((float) $drawerSummary['non_cash_sales'], 2));
        $this->assertSame(2, $shiftSummary['total_orders']);
        $this->assertSame(130.0, round((float) $shiftSummary['gross_revenue'], 2));
        $this->assertSame(80.0, round((float) ($shiftSummary['payment_breakdown'][PaymentMethod::TalabatPay->value] ?? 0), 2));

        $this->actingAs($admin);

        $shiftPage = Livewire::test(ViewShift::class, ['record' => $shift->id])->instance();
        $drawerPage = Livewire::test(ViewDrawerSession::class, ['record' => $drawer->id])->instance();

        $this->assertSame('130.00 ج.م', $shiftPage->getPrimaryStats()[0]['value']);
        $this->assertSame('130.00 ج.م', $drawerPage->getPrimaryStats()[0]['value']);
        $this->assertSame('80.00 ج.م', $shiftPage->getPrimaryStats()[2]['value']);
        $this->assertSame('80.00 ج.م', $drawerPage->getSecondaryStats()[1]['value']);
        $this->assertSame('65.00 ج.م', $shiftPage->getSecondaryStats()[1]['value']);
        $this->assertSame('65.00 ج.م', $drawerPage->getSecondaryStats()[4]['value']);
    }

    protected function createSessionContext(): array
    {
        $this->artisan('db:seed');

        $cashier = User::factory()->create([
            'name' => 'Cashier Tester',
            'username' => 'cashier-tester',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstWhere('name', 'cashier');
        $cashier->roles()->sync([$cashierRole->id]);

        $admin = User::where('email', 'admin@pos.com')->first();
        $adminRole = Role::firstWhere('name', 'admin');
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-NON-CASH-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $admin->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Non Cash',
            'identifier' => 'POS-NON-CASH-1',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRAWER-NON-CASH-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $admin->id,
            'opening_balance' => 0,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        return [$cashier->fresh(), $admin->fresh(), $shift->fresh(), $drawer->fresh()];
    }

    protected function createOrder(
        User $cashier,
        Shift $shift,
        CashierDrawerSession $drawer,
        string $suffix,
        OrderSource $source,
        float $amount,
    ): Order {
        $category = MenuCategory::firstOrCreate(
            ['name' => 'اختبارات غير نقدية'],
            ['is_active' => true]
        );

        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => "وجبة {$suffix}",
            'type' => 'simple',
            'base_price' => $amount,
            'cost_price' => 20,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => "ORD-NON-CASH-{$suffix}",
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => $source,
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $drawer->pos_device_id,
            'subtotal' => $amount,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => $amount,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'item_name' => $item->name,
            'unit_price' => $amount,
            'cost_price' => 20,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => $amount,
            'status' => OrderItemStatus::Pending,
        ]);

        return $order->fresh();
    }
}
