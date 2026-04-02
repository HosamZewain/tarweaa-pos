<?php

namespace Tests\Feature;

use App\DTOs\ProcessPaymentData;
use App\Enums\CashMovementType;
use App\Enums\DrawerSessionStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Filament\Resources\DrawerSessionResource\Pages\ViewDrawerSession;
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
use Livewire\Livewire;
use Tests\TestCase;

class CashChangeDrawerReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_gross_cash_rows_are_normalized_in_drawer_reporting(): void
    {
        [$cashier, $admin, $shift, $drawer] = $this->createSessionContext();
        $order = $this->createOrder($cashier, $shift, $drawer, 'LEGACY-CASH', OrderSource::Pos, 100);

        $order->payments()->create([
            'payment_method' => PaymentMethod::Cash,
            'amount' => 150,
            'fee_amount' => 0,
            'net_settlement_amount' => 150,
            'created_by' => $cashier->id,
            'updated_by' => $cashier->id,
        ]);

        $order->update([
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => 150,
            'change_amount' => 50,
            'confirmed_at' => now(),
        ]);

        $drawer->addMovement(
            type: CashMovementType::Sale,
            amount: 150,
            performedBy: $cashier->id,
            referenceType: 'order',
            referenceId: $order->id,
        );

        $drawerSummary = app(DrawerSessionService::class)->getSessionSummary($drawer->fresh(), $admin->fresh());
        $closePreview = app(DrawerSessionService::class)->getClosePreview($drawer->fresh(), $cashier->fresh(), 100);
        $shiftSummary = app(CashManagementService::class)->getShiftSummary($shift->fresh());

        $this->assertSame(100.0, round((float) $drawerSummary['cash_sales'], 2));
        $this->assertSame(0.0, round((float) $drawerSummary['non_cash_sales'], 2));
        $this->assertSame(100.0, round((float) $drawerSummary['expected_cash'], 2));
        $this->assertSame(100.0, round((float) $shiftSummary['total_expected_cash'], 2));
        $this->assertSame(100.0, round((float) ($shiftSummary['payment_breakdown'][PaymentMethod::Cash->value] ?? 0), 2));
        $this->assertTrue((bool) $closePreview['matches_expected']);

        $this->actingAs($admin);

        $drawerPage = Livewire::test(ViewDrawerSession::class, ['record' => $drawer->id])->instance();

        $this->assertSame('100.00 ج.م', $drawerPage->getSecondaryStats()[0]['value']);
        $this->assertSame('100.00 ج.م', $drawerPage->getFinancialSnapshot()[2]['value']);
        $this->assertSame('100.00 ج.م', $drawerPage->getPrimaryStats()[2]['value']);
    }

    public function test_cash_overpayment_is_recorded_as_net_sale_with_change_separately(): void
    {
        [$cashier, $admin, $shift, $drawer] = $this->createSessionContext();
        $order = $this->createOrder($cashier, $shift, $drawer, 'NET-CASH', OrderSource::Pos, 100);

        app(OrderPaymentService::class)->processPayment(
            order: $order,
            payments: [new ProcessPaymentData(method: PaymentMethod::Cash, amount: 150)],
            actorId: $cashier->id,
        );

        $freshOrder = $order->fresh(['payments']);
        $drawerSummary = app(DrawerSessionService::class)->getSessionSummary($drawer->fresh(), $admin->fresh());

        $this->assertSame('100.00', $freshOrder->paid_amount);
        $this->assertSame('50.00', $freshOrder->change_amount);
        $this->assertSame(100.0, round((float) $freshOrder->payments->first()->amount, 2));
        $this->assertDatabaseHas('cash_movements', [
            'drawer_session_id' => $drawer->id,
            'type' => CashMovementType::Sale->value,
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'amount' => '100.00',
        ]);
        $this->assertSame(100.0, round((float) $drawerSummary['cash_sales'], 2));
        $this->assertSame(100.0, round((float) $drawerSummary['expected_cash'], 2));
    }

    public function test_split_payment_overpayment_only_applies_remaining_amount_to_cash(): void
    {
        [$cashier, $admin, $shift, $drawer] = $this->createSessionContext();
        $order = $this->createOrder($cashier, $shift, $drawer, 'SPLIT-CASH', OrderSource::Pos, 100);

        app(OrderPaymentService::class)->processPayment(
            order: $order,
            payments: [
                new ProcessPaymentData(method: PaymentMethod::TalabatPay, amount: 40, referenceNumber: 'TALABAT-40'),
                new ProcessPaymentData(method: PaymentMethod::Cash, amount: 70),
            ],
            actorId: $cashier->id,
        );

        $freshOrder = $order->fresh(['payments']);
        $drawerSummary = app(DrawerSessionService::class)->getSessionSummary($drawer->fresh(), $admin->fresh());

        $this->assertSame('100.00', $freshOrder->paid_amount);
        $this->assertSame('10.00', $freshOrder->change_amount);
        $this->assertSame(40.0, round((float) $freshOrder->payments->firstWhere('payment_method', PaymentMethod::TalabatPay)->amount, 2));
        $this->assertSame(60.0, round((float) $freshOrder->payments->firstWhere('payment_method', PaymentMethod::Cash)->amount, 2));
        $this->assertSame(60.0, round((float) $drawerSummary['cash_sales'], 2));
        $this->assertSame(40.0, round((float) $drawerSummary['non_cash_sales'], 2));
        $this->assertSame(60.0, round((float) $drawerSummary['expected_cash'], 2));
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
            'shift_number' => 'SHIFT-CHANGE-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $admin->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Change Test',
            'identifier' => 'POS-CHANGE-1',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRAWER-CHANGE-001',
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
            ['name' => 'اختبارات الباقي النقدي'],
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
            'order_number' => "ORD-CHANGE-{$suffix}",
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
