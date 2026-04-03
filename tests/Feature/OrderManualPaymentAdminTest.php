<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\DTOs\ProcessPaymentData;
use App\Enums\CashMovementType;
use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Exceptions\OrderException;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\AdminActivityLog;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\OrderCreationService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderManualPaymentAdminTest extends TestCase
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
            'name' => 'Cashier Manual Payment',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstWhere('name', 'cashier');
        $this->cashier->roles()->sync([$cashierRole->id]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-MANUAL-PAY-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Manual Payment',
            'identifier' => 'POS-MANUAL-PAY-001',
            'is_active' => true,
        ]);

        $this->drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-MANUAL-PAY-001',
            'cashier_id' => $this->cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->adminUser->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $this->cashier->id,
            'drawer_session_id' => $this->drawer->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_admin_can_record_manual_payment_for_unpaid_order_and_log_it(): void
    {
        $order = $this->createUnpaidOrder(totalPrice: 55);

        Livewire::actingAs($this->adminUser)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('recordPayment', data: [
                'payment_method' => PaymentMethod::Cash->value,
                'notes' => 'تم التحصيل بالفعل خارج شاشة نقطة البيع',
            ]);

        $order->refresh();

        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('55.00', $order->paid_amount);
        $this->assertSame('confirmed', $order->status->value);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::Cash->value,
            'amount' => '55.00',
        ]);

        $this->assertDatabaseHas('cash_movements', [
            'drawer_session_id' => $this->drawer->id,
            'type' => CashMovementType::Sale->value,
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'amount' => '55.00',
        ]);

        $activity = AdminActivityLog::query()
            ->where('action', 'manual_payment_recorded')
            ->where('subject_id', $order->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->adminUser->id, $activity->actor_user_id);
        $this->assertSame(PaymentMethod::Cash->value, $activity->new_values['payment_method']);
        $this->assertSame(55.0, (float) $activity->new_values['recorded_amount']);
        $this->assertSame('تم التحصيل بالفعل خارج شاشة نقطة البيع', $activity->new_values['notes']);
    }

    public function test_cannot_process_payment_when_drawer_session_is_closed(): void
    {
        $order = $this->createUnpaidOrder(totalPrice: 40);

        $this->drawer->update([
            'status' => DrawerSessionStatus::Closed,
            'ended_at' => now(),
        ]);

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('جلسة الدرج الخاصة بهذا الطلب مغلقة بالفعل. لا يمكن تعديل الطلب بعد إغلاق الجلسة.');

        app(OrderPaymentService::class)->processPayment(
            order: $order->fresh(['drawerSession', 'settlement']),
            payments: [
                new ProcessPaymentData(
                    method: PaymentMethod::Cash,
                    amount: 40,
                ),
            ],
            actorId: $this->adminUser->id,
        );
    }

    private function createUnpaidOrder(float $totalPrice): \App\Models\Order
    {
        $category = MenuCategory::create([
            'name' => 'اختبار الدفع اليدوي',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'طلب يدوي',
            'type' => 'simple',
            'base_price' => $totalPrice,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = app(OrderCreationService::class)->create(
            cashier: $this->cashier,
            data: CreateOrderData::fromArray([
                'type' => OrderType::Takeaway->value,
                'source' => OrderSource::Pos->value,
                'tax_rate' => 0,
            ]),
        );

        app(OrderCreationService::class)->addItem(
            order: $order,
            data: AddOrderItemData::fromArray([
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ]),
            actorId: $this->cashier->id,
        );

        return $order->fresh(['drawerSession', 'settlement']);
    }
}
