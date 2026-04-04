<?php

namespace Tests\Feature;

use App\Enums\ChannelPricingRuleType;
use App\Enums\CashMovementType;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerminalFeeType;
use App\Models\CashMovement;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemModifier;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\OrderItemModifier;
use App\Models\OrderPayment;
use App\Models\PaymentTerminal;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosUiContractCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $cashierUser;
    private PosDevice $device;
    private PaymentTerminal $terminal;
    private PosOrderType $defaultOrderType;
    private MenuCategory $category;
    private MenuItem $simpleItem;
    private MenuItem $variableItem;
    private MenuItemVariant $largeVariant;
    private ModifierGroup $extrasGroup;
    private MenuItemModifier $cheeseModifier;
    private MenuItemModifier $sauceModifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        Shift::query()->where('status', 'open')->update([
            'status' => 'closed',
            'ended_at' => now(),
            'expected_cash' => 0,
            'actual_cash' => 0,
            'cash_difference' => 0,
        ]);

        \App\Models\CashierDrawerSession::query()->where('status', 'open')->update([
            'status' => 'closed',
            'ended_at' => now(),
            'closing_balance' => 0,
            'expected_balance' => 0,
            'cash_difference' => 0,
        ]);

        \App\Models\CashierActiveSession::query()->delete();

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();

        $this->cashierUser = User::factory()->create([
            'name' => 'POS UI Cashier',
            'username' => 'pos-ui-cashier',
            'pin' => '1357',
            'is_active' => true,
        ]);
        $this->cashierUser->roles()->sync([Role::firstWhere('name', 'cashier')->id]);

        $this->device = PosDevice::create([
            'name' => 'POS UI Device',
            'identifier' => 'POS-UI-001',
            'is_active' => true,
        ]);

        $this->terminal = PaymentTerminal::create([
            'name' => 'POS UI Terminal',
            'bank_name' => 'QNB',
            'code' => 'QNB-POS-UI-001',
            'fee_type' => PaymentTerminalFeeType::PercentagePlusFixed,
            'fee_percentage' => 2.50,
            'fee_fixed_amount' => 1.50,
            'is_active' => true,
        ]);

        $this->defaultOrderType = PosOrderType::create([
            'name' => 'صالة',
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pricing_rule_type' => ChannelPricingRuleType::BasePrice,
            'pricing_rule_value' => 0,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $this->category = MenuCategory::create([
            'name' => 'POS UI Menu',
            'is_active' => true,
        ]);

        $this->simpleItem = MenuItem::create([
            'category_id' => $this->category->id,
            'name' => 'بطاطس UI',
            'type' => 'simple',
            'base_price' => 25,
            'cost_price' => 8,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->variableItem = MenuItem::create([
            'category_id' => $this->category->id,
            'name' => 'برجر UI',
            'type' => 'variable',
            'base_price' => 70,
            'cost_price' => 30,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->largeVariant = MenuItemVariant::create([
            'menu_item_id' => $this->variableItem->id,
            'name' => 'كبير',
            'sku' => 'UI-BRG-L',
            'price' => 85,
            'cost_price' => 35,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $this->extrasGroup = ModifierGroup::create([
            'name' => 'إضافات',
            'selection_type' => 'multiple',
            'is_required' => false,
            'min_selections' => 0,
            'max_selections' => 3,
            'sort_order' => 1,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->cheeseModifier = MenuItemModifier::create([
            'modifier_group_id' => $this->extrasGroup->id,
            'name' => 'جبنة إضافية',
            'price' => 10,
            'cost_price' => 3,
            'is_available' => true,
            'sort_order' => 1,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->sauceModifier = MenuItemModifier::create([
            'modifier_group_id' => $this->extrasGroup->id,
            'name' => 'صوص إضافي',
            'price' => 5,
            'cost_price' => 1,
            'is_available' => true,
            'sort_order' => 2,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->variableItem->modifierGroups()->sync([$this->extrasGroup->id => ['sort_order' => 1]]);
    }

    public function test_pos_supports_split_cash_and_card_payment_through_order_api(): void
    {
        [$shiftId, $drawerId] = $this->openShiftAndDrawer();

        Sanctum::actingAs($this->cashierUser);

        $orderId = $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pos_order_type_id' => $this->defaultOrderType->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/orders/{$orderId}/items", [
            'menu_item_id' => $this->simpleItem->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.order.total', '50.00');

        $this->postJson("/api/orders/{$orderId}/pay", [
            'payments' => [
                [
                    'method' => PaymentMethod::Card->value,
                    'amount' => 20,
                    'terminal_id' => $this->terminal->id,
                    'reference_number' => 'CARD-UI-001',
                ],
                [
                    'method' => PaymentMethod::Cash->value,
                    'amount' => 30,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.paid_amount', '50.00')
            ->assertJsonPath('data.change_amount', '0.00');

        $payments = OrderPayment::query()->where('order_id', $orderId)->orderBy('id')->get();

        $this->assertCount(2, $payments);
        $this->assertSame(PaymentMethod::Card->value, $payments[0]->payment_method->value);
        $this->assertSame('20.00', $payments[0]->amount);
        $this->assertSame($this->terminal->id, $payments[0]->terminal_id);
        $this->assertSame(PaymentMethod::Cash->value, $payments[1]->payment_method->value);
        $this->assertSame('30.00', $payments[1]->amount);

        $this->assertDatabaseHas('cash_movements', [
            'drawer_session_id' => $drawerId,
            'type' => CashMovementType::Sale->value,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'amount' => '30.00',
        ]);

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.shift.id', $shiftId)
            ->assertJsonPath('data.drawer_session_id', $drawerId)
            ->assertJsonPath('data.payments.0.terminal.id', $this->terminal->id)
            ->assertJsonPath('data.payments.0.reference_number', 'CARD-UI-001');
    }

    public function test_pos_supports_variant_modifier_and_note_snapshots_for_order_lines(): void
    {
        $this->openShiftAndDrawer();

        Sanctum::actingAs($this->cashierUser);

        $orderId = $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pos_order_type_id' => $this->defaultOrderType->id,
        ])->assertCreated()->json('data.id');

        $firstResponse = $this->postJson("/api/orders/{$orderId}/items", [
            'menu_item_id' => $this->variableItem->id,
            'variant_id' => $this->largeVariant->id,
            'quantity' => 1,
            'modifiers' => [
                $this->cheeseModifier->id => 1,
                $this->sauceModifier->id => 2,
            ],
            'notes' => 'بدون بصل',
        ])->assertCreated();

        $this->assertSame('105.00', $firstResponse->json('data.item.total'));
        $this->assertSame('105.00', $firstResponse->json('data.order.total'));

        $secondResponse = $this->postJson("/api/orders/{$orderId}/items", [
            'menu_item_id' => $this->variableItem->id,
            'variant_id' => $this->largeVariant->id,
            'quantity' => 1,
            'modifiers' => [
                $this->cheeseModifier->id => 1,
            ],
            'notes' => 'حار',
        ])->assertCreated();

        $this->assertSame('95.00', $secondResponse->json('data.item.total'));
        $this->assertSame('200.00', $secondResponse->json('data.order.total'));

        $order = Order::query()->with(['items.modifiers'])->findOrFail($orderId);

        $this->assertCount(2, $order->items);
        $this->assertSame('كبير', $order->items[0]->variant_name);
        $this->assertSame('بدون بصل', $order->items[0]->notes);
        $this->assertSame('حار', $order->items[1]->notes);

        $modifierNames = OrderItemModifier::query()
            ->where('order_item_id', $order->items[0]->id)
            ->orderBy('id')
            ->pluck('modifier_name')
            ->all();

        $this->assertSame(['جبنة إضافية', 'صوص إضافي'], $modifierNames);
        $this->assertDatabaseHas('order_item_modifiers', [
            'order_item_id' => $order->items[0]->id,
            'menu_item_modifier_id' => $this->sauceModifier->id,
            'price' => '5.00',
            'quantity' => 2,
        ]);

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.notes', 'بدون بصل')
            ->assertJsonPath('data.items.1.notes', 'حار');
    }

    public function test_pos_session_order_listing_and_reprint_dependencies_are_available(): void
    {
        [, $drawerId] = $this->openShiftAndDrawer();

        Sanctum::actingAs($this->cashierUser);

        $paidOrderId = $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pos_order_type_id' => $this->defaultOrderType->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/orders/{$paidOrderId}/items", [
            'menu_item_id' => $this->simpleItem->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson("/api/orders/{$paidOrderId}/pay", [
            'payments' => [
                [
                    'method' => PaymentMethod::Cash->value,
                    'amount' => 25,
                ],
            ],
        ])->assertOk();

        $unpaidOrderId = $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pos_order_type_id' => $this->defaultOrderType->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/orders/{$unpaidOrderId}/items", [
            'menu_item_id' => $this->simpleItem->id,
            'quantity' => 2,
        ])->assertCreated();

        $response = $this->getJson("/api/orders?drawer_session_id={$drawerId}&per_page=50")
            ->assertOk()
            ->json('data');

        $orders = collect($response)->keyBy('id');

        $this->assertSame(PaymentStatus::Paid->value, $orders[$paidOrderId]['payment_status']);
        $this->assertSame(PaymentStatus::Unpaid->value, $orders[$unpaidOrderId]['payment_status']);

        $this->getJson("/api/orders/{$paidOrderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $paidOrderId)
            ->assertJsonPath('data.order_number', Order::findOrFail($paidOrderId)->order_number)
            ->assertJsonPath('data.cashier.id', $this->cashierUser->id)
            ->assertJsonPath('data.payments.0.payment_method', PaymentMethod::Cash->value)
            ->assertJsonPath('data.items.0.item_name', 'بطاطس UI');
    }

    public function test_pos_page_contains_session_modal_reprint_and_two_copy_receipt_contract(): void
    {
        $this->actingAs($this->cashierUser)
            ->get('/pos')
            ->assertSuccessful()
            ->assertSee('id="tab-session-orders"', false)
            ->assertSee('id="session-orders-list"', false)
            ->assertSee("const receiptCopies = ['نسخة العميل', 'نسخة المحل'];", false)
            ->assertSee('async function printPaidOrderReceipt(orderId', false)
            ->assertSee('function canReprintSessionOrder(order)', false)
            ->assertSee('async function reprintSessionOrder(event, orderId)', false);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function openShiftAndDrawer(): array
    {
        Sanctum::actingAs($this->adminUser);

        $shiftId = $this->postJson('/api/shifts/open', [
            'notes' => 'فتح وردية تغطية POS',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->cashierUser);

        $drawerId = $this->postJson('/api/drawers/open', [
            'shift_id' => $shiftId,
            'pos_device_id' => $this->device->id,
            'opening_balance' => 100,
        ])->assertCreated()->json('data.id');

        return [$shiftId, $drawerId];
    }
}
