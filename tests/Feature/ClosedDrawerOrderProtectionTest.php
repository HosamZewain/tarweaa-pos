<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\DTOs\ProcessPaymentData;
use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Exceptions\OrderException;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\OrderCreationService;
use App\Services\OrderDeletionService;
use App\Services\OrderLifecycleService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosedDrawerOrderProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
    }

    public function test_cannot_cancel_or_delete_order_after_drawer_is_closed(): void
    {
        [$cashier, $order, $drawer] = $this->createPaidOrder();
        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        $drawer->update([
            'status' => DrawerSessionStatus::Closed,
            'closing_balance' => 280,
            'expected_balance' => 280,
            'cash_difference' => 0,
            'closed_by' => $admin->id,
            'ended_at' => now(),
        ]);

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('جلسة الدرج');
        app(OrderLifecycleService::class)->cancel($order->fresh(), $admin, 'محاولة تعديل جلسة مغلقة');
    }

    public function test_cannot_delete_order_after_drawer_is_closed(): void
    {
        [$cashier, $order, $drawer] = $this->createPaidOrder();
        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        $drawer->update([
            'status' => DrawerSessionStatus::Closed,
            'closing_balance' => 280,
            'expected_balance' => 280,
            'cash_difference' => 0,
            'closed_by' => $admin->id,
            'ended_at' => now(),
        ]);

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('جلسة الدرج');
        app(OrderDeletionService::class)->deleteWithReversal($order->fresh(), $admin, 'محاولة حذف جلسة مغلقة');
    }

    public function test_closed_drawer_uses_stored_expected_balance_snapshot(): void
    {
        [$cashier, $order, $drawer] = $this->createPaidOrder();
        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        $drawer->update([
            'status' => DrawerSessionStatus::Closed,
            'closing_balance' => 260,
            'expected_balance' => 260,
            'cash_difference' => 0,
            'closed_by' => $admin->id,
            'ended_at' => now(),
        ]);

        $order->update([
            'refund_amount' => 50,
        ]);

        $this->assertSame(260.0, $drawer->fresh()->calculateExpectedBalance());
    }

    private function createPaidOrder(): array
    {
        $cashier = User::factory()->create([
            'name' => 'Closed Drawer Cashier',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier', 'is_active' => true],
        );
        $cashier->roles()->syncWithoutDetaching([$cashierRole->id]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-CLOSED-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Closed',
            'identifier' => 'POS-CLOSED-01',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-CLOSED-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now()->subHour(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        $category = MenuCategory::create([
            'name' => 'Closed Drawer Meals',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Closed Drawer Meal',
            'type' => 'simple',
            'base_price' => 180,
            'cost_price' => 20,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = app(OrderCreationService::class)->create(
            cashier: $cashier,
            data: CreateOrderData::fromArray([
                'type' => OrderType::Takeaway->value,
                'source' => OrderSource::Pos->value,
            ]),
        );

        app(OrderCreationService::class)->addItem(
            order: $order,
            data: AddOrderItemData::fromArray([
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ]),
            actorId: $cashier->id,
        );

        app(OrderPaymentService::class)->processPayment(
            order: $order->fresh(),
            payments: [
                new ProcessPaymentData(method: PaymentMethod::Cash, amount: 180),
            ],
            actorId: $cashier->id,
        );

        return [$cashier, $order->fresh(), $drawer->fresh()];
    }
}
