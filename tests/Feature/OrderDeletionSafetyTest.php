<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\DTOs\ProcessPaymentData;
use App\Enums\DrawerSessionStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\MealBenefitLedgerEntryType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Exceptions\OrderException;
use App\Models\CashierDrawerSession;
use App\Models\CashMovement;
use App\Models\CashierActiveSession;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\MealBenefitLedgerEntry;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemRecipeLine;
use App\Models\Order;
use App\Models\OrderSettlement;
use App\Models\OrderSettlementLine;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Services\OrderCreationService;
use App\Services\OrderDeletionService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
    }

    public function test_safe_delete_reverses_recipe_stock_cash_and_customer_totals_then_soft_deletes_order(): void
    {
        [$cashier, $order, $orderItem, $inventoryItem, $customer] = $this->createPaidRecipeOrder();

        $actor = User::where('email', 'admin@pos.com')->firstOrFail();

        app(OrderDeletionService::class)->deleteWithReversal($order->fresh(), $actor, 'تنظيف طلب منشأ بالخطأ');

        $order = Order::withTrashed()->findOrFail($order->id);
        $orderItem->refresh();
        $inventoryItem->refresh();
        $customer->refresh();

        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertNull($orderItem->stock_deducted_at);
        $this->assertSame('5.000', $inventoryItem->current_stock);
        $this->assertSame('5.000', $restaurantStock->current_stock);
        $this->assertSame(0, (int) $customer->total_orders);
        $this->assertSame('0.00', number_format((float) $customer->total_spent, 2, '.', ''));

        $this->assertDatabaseHas('cash_movements', [
            'drawer_session_id' => $order->drawer_session_id,
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'type' => 'refund',
            'amount' => '360.00',
        ]);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'type' => InventoryTransactionType::Return->value,
            'reference_type' => 'order_item',
            'reference_id' => $orderItem->id,
            'quantity' => '0.400',
        ]);
    }

    public function test_safe_delete_removes_settlement_and_meal_benefit_ledger_entries(): void
    {
        $actor = User::where('email', 'admin@pos.com')->firstOrFail();
        $shift = Shift::create([
            'shift_number' => 'SHIFT-SETTLEMENT-DELETE',
            'status' => ShiftStatus::Open,
            'opened_by' => $actor->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Settlement Delete',
            'identifier' => 'POS-SETTLEMENT-DELETE',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-SETTLEMENT-DELETE',
            'cashier_id' => $actor->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $actor->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now()->subHour(),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SETTLEMENT-DELETE',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $actor->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 100,
            'paid_amount' => 100,
            'refund_amount' => 0,
        ]);

        $beneficiary = User::factory()->create([
            'is_active' => true,
            'password' => Hash::make('secret123'),
        ]);

        $profile = UserMealBenefitProfile::create([
            'user_id' => $beneficiary->id,
            'is_active' => true,
            'can_receive_owner_charge_orders' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $settlement = OrderSettlement::create([
            'order_id' => $order->id,
            'settlement_type' => 'owner_charge',
            'beneficiary_user_id' => $beneficiary->id,
            'charge_account_user_id' => $beneficiary->id,
            'commercial_total_amount' => 100,
            'covered_amount' => 100,
            'remaining_payable_amount' => 0,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $line = OrderSettlementLine::create([
            'order_settlement_id' => $settlement->id,
            'order_id' => $order->id,
            'line_type' => 'owner_charge',
            'user_id' => $beneficiary->id,
            'profile_id' => $profile->id,
            'eligible_amount' => 100,
            'covered_amount' => 100,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        MealBenefitLedgerEntry::create([
            'user_id' => $beneficiary->id,
            'profile_id' => $profile->id,
            'order_id' => $order->id,
            'order_settlement_line_id' => $line->id,
            'entry_type' => MealBenefitLedgerEntryType::OwnerChargeUsage,
            'amount' => 100,
            'notes' => 'test',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        app(OrderDeletionService::class)->deleteWithReversal($order->fresh(), $actor, 'حذف طلب تسوية');

        $this->assertDatabaseMissing('order_settlements', ['id' => $settlement->id]);
        $this->assertDatabaseMissing('order_settlement_lines', ['id' => $line->id]);
        $this->assertDatabaseMissing('meal_benefit_ledger_entries', ['order_id' => $order->id]);
    }

    public function test_safe_delete_is_blocked_for_non_cash_payments(): void
    {
        [$cashier, $order] = $this->createBasicPaidOrder(PaymentMethod::TalabatPay, 100, 'TAL-DELETE-001');
        $actor = User::where('email', 'admin@pos.com')->firstOrFail();

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('دفعات غير نقدية');

        app(OrderDeletionService::class)->deleteWithReversal($order->fresh(), $actor, 'محاولة حذف طلب طلبات');
    }

    private function createPaidRecipeOrder(): array
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier Delete Order',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier', 'is_active' => true],
        );
        $cashier->roles()->syncWithoutDetaching([$cashierRole->id]);

        $customer = \App\Models\Customer::create([
            'name' => 'عميل حذف',
            'phone' => '01000000001',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-DELETE-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Delete',
            'identifier' => 'POS-DELETE-01',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-DELETE-001',
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

        $inventoryItem = InventoryItem::create([
            'name' => 'جبنة موزاريلا',
            'sku' => 'MOZZ-DELETE',
            'category' => 'أجبان',
            'unit' => 'كجم',
            'unit_cost' => 200,
            'current_stock' => 5,
            'minimum_stock' => 1,
            'maximum_stock' => 10,
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'name' => 'بيتزا حذف',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'بيتزا جبنة',
            'type' => 'simple',
            'base_price' => 180,
            'is_available' => true,
            'is_active' => true,
        ]);

        MenuItemRecipeLine::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 200,
            'unit' => 'جم',
            'unit_conversion_rate' => 0.001,
        ]);

        $order = app(OrderCreationService::class)->create(
            cashier: $cashier,
            data: CreateOrderData::fromArray([
                'type' => OrderType::Takeaway->value,
                'source' => OrderSource::Pos->value,
                'customer_id' => $customer->id,
                'tax_rate' => 0,
            ]),
        );

        $orderItem = app(OrderCreationService::class)->addItem(
            order: $order,
            data: AddOrderItemData::fromArray([
                'menu_item_id' => $menuItem->id,
                'quantity' => 2,
            ]),
            actorId: $cashier->id,
        );

        app(OrderPaymentService::class)->processPayment(
            order: $order->fresh(),
            payments: [
                new ProcessPaymentData(method: PaymentMethod::Cash, amount: 360),
            ],
            actorId: $cashier->id,
        );

        return [$cashier, $order->fresh(), $orderItem->fresh(), $inventoryItem->fresh(), $customer->fresh()];
    }

    private function createBasicPaidOrder(PaymentMethod $paymentMethod, float $amount, ?string $reference = null): array
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier Basic Delete',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier', 'is_active' => true],
        );
        $cashier->roles()->syncWithoutDetaching([$cashierRole->id]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-BASIC-DELETE-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Basic Delete',
            'identifier' => 'POS-BASIC-DELETE-01',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-BASIC-DELETE-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now()->subHour(),
        ]);

        $category = MenuCategory::create([
            'name' => 'وجبات حذف أساسي',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'وجبة أساسية',
            'type' => 'simple',
            'base_price' => $amount,
            'cost_price' => 20,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-BASIC-DELETE-001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
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
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'وجبة أساسية',
            'unit_price' => $amount,
            'cost_price' => 20,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => $amount,
            'status' => \App\Enums\OrderItemStatus::Pending,
        ]);

        app(OrderPaymentService::class)->processPayment(
            order: $order->fresh(),
            payments: [
                new ProcessPaymentData(method: $paymentMethod, amount: $amount, referenceNumber: $reference),
            ],
            actorId: $cashier->id,
        );

        return [$cashier, $order->fresh(), $drawer];
    }
}
