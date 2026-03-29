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
use App\Enums\PaymentTerminalFeeType;
use App\Enums\ShiftStatus;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTerminal;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Services\PaymentTerminalFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CardTerminalPaymentSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_payment_requires_terminal_and_reference_number(): void
    {
        [$cashier, $order] = $this->createOrderContext();

        Sanctum::actingAs($cashier);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'payments' => [
                [
                    'method' => PaymentMethod::Card->value,
                    'amount' => 100,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'payments.0.terminal_id',
                'payments.0.reference_number',
            ]);
    }

    public function test_talabat_pay_requires_talabat_order_number(): void
    {
        [$cashier, $order] = $this->createOrderContext('TAL-VAL');

        Sanctum::actingAs($cashier);

        $this->postJson("/api/orders/{$order->id}/pay", [
            'payments' => [
                [
                    'method' => PaymentMethod::TalabatPay->value,
                    'amount' => 100,
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'payments.0.reference_number',
            ]);
    }

    public function test_instapay_requires_sender_phone_number(): void
    {
        [$cashier, $order] = $this->createOrderContext('INSTA-VAL');

        Sanctum::actingAs($cashier);

        $this->postJson("/api/orders/{$order->id}/pay", [
            'payments' => [
                [
                    'method' => PaymentMethod::InstaPay->value,
                    'amount' => 100,
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'payments.0.reference_number',
            ]);
    }

    public function test_pos_card_preview_returns_backend_fee_calculation(): void
    {
        [$cashier] = $this->createOrderContext();

        $terminal = PaymentTerminal::create([
            'name' => 'CIB Main',
            'bank_name' => 'CIB',
            'code' => 'CIB-002',
            'fee_type' => PaymentTerminalFeeType::PercentagePlusFixed,
            'fee_percentage' => 2.50,
            'fee_fixed_amount' => 1.50,
            'is_active' => true,
        ]);

        Sanctum::actingAs($cashier);

        $response = $this->postJson('/api/pos/payment-preview', [
            'method' => PaymentMethod::Card->value,
            'amount' => 100,
            'terminal_id' => $terminal->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.paid_amount', 100)
            ->assertJsonPath('data.fee_amount', 4)
            ->assertJsonPath('data.net_settlement_amount', 96)
            ->assertJsonPath('data.terminal.id', $terminal->id);
    }

    public function test_report_service_distinguishes_card_totals_by_terminal(): void
    {
        [$cashier, $orderOne] = $this->createOrderContext('001');
        [, $orderTwo] = $this->createOrderContext('002');

        $terminalOne = PaymentTerminal::create([
            'name' => 'CIB Main',
            'bank_name' => 'CIB',
            'code' => 'CIB-REP-1',
            'fee_type' => PaymentTerminalFeeType::Percentage,
            'fee_percentage' => 2.5,
            'fee_fixed_amount' => 0,
            'is_active' => true,
        ]);

        $terminalTwo = PaymentTerminal::create([
            'name' => 'QNB Main',
            'bank_name' => 'QNB',
            'code' => 'QNB-REP-1',
            'fee_type' => PaymentTerminalFeeType::Fixed,
            'fee_percentage' => 0,
            'fee_fixed_amount' => 3,
            'is_active' => true,
        ]);

        app(OrderPaymentService::class)->processPayment(
            order: $orderOne,
            payments: [
                new ProcessPaymentData(
                    method: PaymentMethod::Card,
                    amount: 100,
                    terminalId: $terminalOne->id,
                    referenceNumber: 'REP-100',
                ),
            ],
            actorId: $cashier->id,
        );

        app(OrderPaymentService::class)->processPayment(
            order: $orderTwo,
            payments: [
                new ProcessPaymentData(
                    method: PaymentMethod::Card,
                    amount: 100,
                    terminalId: $terminalTwo->id,
                    referenceNumber: 'REP-200',
                ),
            ],
            actorId: $cashier->id,
        );

        $report = app(\App\Services\ReportService::class)->getCardPaymentsByTerminal();

        $this->assertCount(2, $report['terminals']);
        $this->assertSame(200.0, $report['totals']['total_paid_amount']);
        $this->assertSame(5.5, $report['totals']['total_fee_amount']);
        $this->assertSame(194.5, $report['totals']['total_net_settlement']);

        $byName = collect($report['terminals'])->keyBy('terminal_name');

        $this->assertSame(100.0, $byName['CIB Main']['total_paid_amount']);
        $this->assertSame(2.5, $byName['CIB Main']['total_fee_amount']);
        $this->assertSame(97.5, $byName['CIB Main']['total_net_settlement']);

        $this->assertSame(100.0, $byName['QNB Main']['total_paid_amount']);
        $this->assertSame(3.0, $byName['QNB Main']['total_fee_amount']);
        $this->assertSame(97.0, $byName['QNB Main']['total_net_settlement']);
    }

    public function test_card_payment_persists_terminal_fee_and_net_settlement_without_cash_drawer_movement(): void
    {
        [$cashier, $order, $drawer] = $this->createOrderContext();

        $terminal = PaymentTerminal::create([
            'name' => 'CIB Main',
            'bank_name' => 'CIB',
            'code' => 'CIB-001',
            'fee_type' => PaymentTerminalFeeType::PercentagePlusFixed,
            'fee_percentage' => 2.50,
            'fee_fixed_amount' => 1.50,
            'is_active' => true,
        ]);

        $processed = app(OrderPaymentService::class)->processPayment(
            order: $order,
            payments: [
                new ProcessPaymentData(
                    method: PaymentMethod::Card,
                    amount: 100,
                    terminalId: $terminal->id,
                    referenceNumber: 'RCPT-12345',
                ),
            ],
            actorId: $cashier->id,
        );

        $this->assertSame(OrderStatus::Confirmed, $processed->status);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::Card->value,
            'terminal_id' => $terminal->id,
            'reference_number' => 'RCPT-12345',
            'fee_amount' => '4.00',
            'net_settlement_amount' => '96.00',
        ]);

        $this->assertDatabaseMissing('cash_movements', [
            'drawer_session_id' => $drawer->id,
            'type' => 'sale',
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);
    }

    public function test_talabat_pay_is_recorded_as_non_cash_and_does_not_affect_drawer_cash(): void
    {
        [$cashier, $order, $drawer] = $this->createOrderContext('TAL');

        $processed = app(OrderPaymentService::class)->processPayment(
            order: $order,
            payments: [
                new ProcessPaymentData(
                    method: PaymentMethod::TalabatPay,
                    amount: 100,
                    referenceNumber: 'TAL-12345',
                ),
            ],
            actorId: $cashier->id,
        );

        $this->assertSame(OrderStatus::Confirmed, $processed->status);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::TalabatPay->value,
            'amount' => '100.00',
            'reference_number' => 'TAL-12345',
        ]);

        $this->assertDatabaseMissing('cash_movements', [
            'drawer_session_id' => $drawer->id,
            'type' => 'sale',
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);

        $paymentBreakdown = app(\App\Services\ReportService::class)->getSalesByPaymentMethod();

        $talabatPayRow = $paymentBreakdown->firstWhere('payment_method', PaymentMethod::TalabatPay->value);

        $this->assertNotNull($talabatPayRow);
        $this->assertSame('100.00', number_format((float) $talabatPayRow->total_amount, 2, '.', ''));
    }

    public function test_instapay_is_recorded_as_non_cash_and_does_not_affect_drawer_cash(): void
    {
        [$cashier, $order, $drawer] = $this->createOrderContext('INSTA');

        $processed = app(OrderPaymentService::class)->processPayment(
            order: $order,
            payments: [
                new ProcessPaymentData(
                    method: PaymentMethod::InstaPay,
                    amount: 100,
                    referenceNumber: '01001234567',
                ),
            ],
            actorId: $cashier->id,
        );

        $this->assertSame(OrderStatus::Confirmed, $processed->status);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::InstaPay->value,
            'amount' => '100.00',
            'reference_number' => '01001234567',
        ]);

        $this->assertDatabaseMissing('cash_movements', [
            'drawer_session_id' => $drawer->id,
            'type' => 'sale',
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);
    }

    public function test_order_show_returns_payment_terminal_details_for_receipt_printing(): void
    {
        [$cashier, $order] = $this->createOrderContext('003');

        $terminal = PaymentTerminal::create([
            'name' => 'Receipt Terminal',
            'bank_name' => 'Bank Misr',
            'code' => 'RCPT-TERM-1',
            'fee_type' => PaymentTerminalFeeType::Fixed,
            'fee_percentage' => 0,
            'fee_fixed_amount' => 2,
            'is_active' => true,
        ]);

        app(OrderPaymentService::class)->processPayment(
            order: $order,
            payments: [
                new ProcessPaymentData(
                    method: PaymentMethod::Card,
                    amount: 100,
                    terminalId: $terminal->id,
                    referenceNumber: 'SHOW-123',
                ),
            ],
            actorId: $cashier->id,
        );

        Sanctum::actingAs($cashier);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payments.0.reference_number', 'SHOW-123')
            ->assertJsonPath('data.payments.0.terminal.id', $terminal->id)
            ->assertJsonPath('data.payments.0.terminal.name', 'Receipt Terminal');
    }

    public function test_terminal_fee_service_supports_all_fee_types(): void
    {
        $service = app(PaymentTerminalFeeService::class);

        $percentage = PaymentTerminal::create([
            'name' => 'Percent Terminal',
            'fee_type' => PaymentTerminalFeeType::Percentage,
            'fee_percentage' => 2.5,
            'fee_fixed_amount' => 0,
            'is_active' => true,
        ]);

        $fixed = PaymentTerminal::create([
            'name' => 'Fixed Terminal',
            'fee_type' => PaymentTerminalFeeType::Fixed,
            'fee_percentage' => 0,
            'fee_fixed_amount' => 3,
            'is_active' => true,
        ]);

        $mixed = PaymentTerminal::create([
            'name' => 'Mixed Terminal',
            'fee_type' => PaymentTerminalFeeType::PercentagePlusFixed,
            'fee_percentage' => 2.5,
            'fee_fixed_amount' => 1.5,
            'is_active' => true,
        ]);

        $this->assertSame(
            ['fee_amount' => 2.5, 'net_settlement_amount' => 97.5],
            $service->calculate($percentage, 100)
        );

        $this->assertSame(
            ['fee_amount' => 3.0, 'net_settlement_amount' => 97.0],
            $service->calculate($fixed, 100)
        );

        $this->assertSame(
            ['fee_amount' => 4.0, 'net_settlement_amount' => 96.0],
            $service->calculate($mixed, 100)
        );
    }

    private function createOrderContext(string $suffix = '001'): array
    {
        $cashier = User::factory()->create([
            'name' => "Cashier {$suffix}",
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier']
        );
        $cashier->roles()->attach($cashierRole->id);

        $shift = Shift::create([
            'shift_number' => "SHIFT-CARD-{$suffix}",
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => "POS Card {$suffix}",
            'identifier' => "POS-CARD-{$suffix}",
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => "DRW-CARD-{$suffix}",
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        $category = MenuCategory::create([
            'name' => 'مدفوعات',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'وجبة',
            'type' => 'simple',
            'base_price' => 100,
            'cost_price' => 35,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => "ORD-CARD-{$suffix}",
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'subtotal' => 100,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 100,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'وجبة',
            'unit_price' => 100,
            'cost_price' => 35,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => 100,
            'status' => OrderItemStatus::Pending,
        ]);

        return [$cashier->fresh(), $order->fresh(), $drawer];
    }
}
