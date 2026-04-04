<?php

namespace Tests\Feature;

use App\Enums\ChannelPricingRuleType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerminalFeeType;
use App\Enums\PaymentStatus;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\PaymentTerminal;
use App\Models\Permission;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosCoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $managerUser;
    private User $cashierUser;
    private PosDevice $device;
    private PaymentTerminal $terminal;
    private PosOrderType $defaultOrderType;
    private PosOrderType $talabatOrderType;
    private MenuCategory $category;
    private MenuItem $simpleItem;
    private MenuItem $variableItem;
    private MenuItemVariant $largeVariant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        CashierActiveSession::query()->delete();

        CashierDrawerSession::query()
            ->where('status', 'open')
            ->update([
                'status' => 'closed',
                'ended_at' => now(),
                'closing_balance' => 0,
                'expected_balance' => 0,
                'cash_difference' => 0,
            ]);

        Shift::query()
            ->where('status', 'open')
            ->update([
                'status' => 'closed',
                'ended_at' => now(),
                'expected_cash' => 0,
                'actual_cash' => 0,
                'cash_difference' => 0,
            ]);

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();

        $this->managerUser = User::factory()->create([
            'name' => 'POS Manager',
            'username' => 'pos-manager',
            'pin' => '2468',
            'is_active' => true,
        ]);
        $this->managerUser->roles()->sync([Role::firstWhere('name', 'manager')->id]);

        $this->cashierUser = User::factory()->create([
            'name' => 'POS Cashier',
            'username' => 'pos-cashier',
            'pin' => '1357',
            'is_active' => true,
        ]);
        $this->cashierUser->roles()->sync([Role::firstWhere('name', 'cashier')->id]);

        $this->device = PosDevice::create([
            'name' => 'POS Core Device',
            'identifier' => 'POS-CORE-001',
            'is_active' => true,
        ]);

        $this->terminal = PaymentTerminal::create([
            'name' => 'CIB Main',
            'bank_name' => 'CIB',
            'code' => 'CIB-CORE-001',
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

        $this->talabatOrderType = PosOrderType::create([
            'name' => 'طلبات',
            'type' => OrderType::Delivery->value,
            'source' => OrderSource::Talabat->value,
            'pricing_rule_type' => ChannelPricingRuleType::BasePrice,
            'pricing_rule_value' => 0,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);

        $this->category = MenuCategory::create([
            'name' => 'POS Core Menu',
            'is_active' => true,
        ]);

        $this->simpleItem = MenuItem::create([
            'category_id' => $this->category->id,
            'name' => 'بطاطس',
            'type' => 'simple',
            'base_price' => 25,
            'cost_price' => 8,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->variableItem = MenuItem::create([
            'category_id' => $this->category->id,
            'name' => 'برجر',
            'type' => 'variable',
            'base_price' => 70,
            'cost_price' => 30,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->largeVariant = MenuItemVariant::create([
            'menu_item_id' => $this->variableItem->id,
            'name' => 'كبير',
            'sku' => 'BRG-L',
            'price' => 85,
            'cost_price' => 35,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_cashier_can_bootstrap_pos_surface_and_support_endpoints(): void
    {
        $this->actingAs($this->cashierUser)
            ->get('/pos/drawer')
            ->assertSuccessful();

        $this->actingAs($this->cashierUser)
            ->get('/pos')
            ->assertSuccessful();

        $this->actingAs($this->cashierUser)
            ->get('/pos/close-drawer')
            ->assertSuccessful();

        Sanctum::actingAs($this->cashierUser);

        $this->getJson('/api/pos/status')
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.block_reason', 'لا توجد جلسة درج مفتوحة لهذا الكاشير');

        $shiftId = $this->openShiftAsAdmin();
        $drawerId = $this->openDrawerAsCashier($shiftId, 150);

        $this->getJson('/api/pos/status')
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.shift.id', $shiftId)
            ->assertJsonPath('data.drawer_session.id', $drawerId);

        $this->getJson('/api/pos/devices')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->device->id]);

        $orderTypesResponse = $this->getJson('/api/pos/order-types')
            ->assertOk();

        $this->assertSame(
            PaymentMethod::TalabatPay->value,
            collect($orderTypesResponse->json('data'))->firstWhere('id', $this->talabatOrderType->id)['contextual_payment_method'] ?? null,
        );

        $this->getJson('/api/pos/menu?pos_order_type_id=' . $this->defaultOrderType->id)
            ->assertOk()
            ->assertJsonPath('data.0.name', $this->category->name)
            ->assertJsonPath('data.0.menu_items.0.name', $this->simpleItem->name);

        $createdCustomer = $this->postJson('/api/pos/customers', [
            'name' => 'عميل اختبار POS',
            'phone' => '01099990001',
            'email' => 'pos.customer@example.com',
            'address' => 'القاهرة',
        ])->assertCreated();

        $this->getJson('/api/pos/customers?search=01099990001')
            ->assertOk()
            ->assertJsonPath('data.0.id', $createdCustomer->json('data.id'))
            ->assertJsonPath('data.0.name', 'عميل اختبار POS');

        $this->getJson('/api/pos/payment-terminals')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->terminal->id);

        $this->postJson('/api/pos/payment-preview', [
            'method' => PaymentMethod::Card->value,
            'amount' => 100,
            'terminal_id' => $this->terminal->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.paid_amount', 100)
            ->assertJsonPath('data.fee_amount', 4)
            ->assertJsonPath('data.net_settlement_amount', 96)
            ->assertJsonPath('data.terminal.id', $this->terminal->id);
    }

    public function test_cashier_can_complete_discounted_cash_order_lifecycle_through_pos_endpoints(): void
    {
        $this->grantCashierPermission('apply_discount', 'تطبيق الخصم');

        $shiftId = $this->openShiftAsAdmin();
        $drawerId = $this->openDrawerAsCashier($shiftId, 100);

        Sanctum::actingAs($this->cashierUser->fresh());

        $customerId = $this->postJson('/api/pos/customers', [
            'name' => 'عميل الطلب',
            'phone' => '01099990002',
        ])->assertCreated()->json('data.id');

        $orderId = $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pos_order_type_id' => $this->defaultOrderType->id,
            'customer_id' => $customerId,
            'notes' => 'طلب تشغيل يومي',
        ])->assertCreated()
            ->assertJsonPath('data.drawer_session_id', $drawerId)
            ->json('data.id');

        $friesItemId = $this->postJson("/api/orders/{$orderId}/items", [
            'menu_item_id' => $this->simpleItem->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.order.subtotal', '25.00')
            ->json('data.item.id');

        $this->postJson("/api/orders/{$orderId}/items", [
            'menu_item_id' => $this->variableItem->id,
            'variant_id' => $this->largeVariant->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.order.subtotal', '195.00');

        $this->deleteJson("/api/orders/items/{$friesItemId}")
            ->assertOk()
            ->assertJsonPath('data.subtotal', '170.00')
            ->assertJsonPath('data.total', '170.00');

        $this->getJson('/api/pos/discount-approvers')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->managerUser->id]);

        $approvalToken = $this->postJson('/api/pos/discount-approval', [
            'approver_id' => $this->managerUser->id,
            'approver_pin' => '2468',
            'type' => 'fixed',
            'value' => 20,
            'reason' => 'حسم عميل دائم',
        ])->assertOk()->json('data.approval_token');

        $this->postJson("/api/orders/{$orderId}/discount", [
            'approval_token' => $approvalToken,
            'type' => 'fixed',
            'value' => 20,
            'reason' => 'حسم عميل دائم',
        ])
            ->assertOk()
            ->assertJsonPath('data.discount_amount', '20.00')
            ->assertJsonPath('data.total', '150.00');

        $this->postJson("/api/orders/{$orderId}/pay", [
            'payments' => [
                [
                    'method' => PaymentMethod::Cash->value,
                    'amount' => 150,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.paid_amount', '150.00');

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.total', '150.00')
            ->assertJsonCount(2, 'data.items');

        $this->getJson('/api/orders?drawer_session_id=' . $drawerId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $orderId);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Cash->value,
            'amount' => '150.00',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'menu_item_variant_id' => $this->largeVariant->id,
            'variant_name' => $this->largeVariant->name,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $friesItemId,
            'status' => OrderItemStatus::Cancelled->value,
        ]);
    }

    public function test_cashier_can_preview_and_apply_special_settlement_through_pos_endpoints(): void
    {
        $this->grantCashierPermission('orders.apply_special_settlement', 'تطبيق تسويات خاصة');

        $owner = User::factory()->create([
            'name' => 'Owner Charge User',
            'username' => 'owner-charge-user',
            'is_active' => true,
        ]);
        $owner->roles()->sync([Role::firstWhere('name', 'owner')->id]);

        UserMealBenefitProfile::query()->create([
            'user_id' => $owner->id,
            'is_active' => true,
            'can_receive_owner_charge_orders' => true,
        ]);

        $shiftId = $this->openShiftAsAdmin();
        $this->openDrawerAsCashier($shiftId, 100);

        Sanctum::actingAs($this->cashierUser->fresh());

        $orderId = $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pos_order_type_id' => $this->defaultOrderType->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/orders/{$orderId}/items", [
            'menu_item_id' => $this->variableItem->id,
            'variant_id' => $this->largeVariant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->getJson('/api/pos/settlement-users?scenario=owner_charge')
            ->assertOk()
            ->assertJsonFragment(['id' => $owner->id]);

        $this->postJson('/api/pos/settlement-preview', [
            'scenario' => 'owner_charge',
            'charge_account_user_id' => $owner->id,
            'items' => [
                [
                    'menu_item_id' => $this->variableItem->id,
                    'quantity' => 1,
                    'variant_id' => $this->largeVariant->id,
                    'modifiers' => [],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.covered_amount', 85)
            ->assertJsonPath('data.remaining_payable_amount', 0)
            ->assertJsonPath('data.can_apply', true);

        $this->postJson("/api/orders/{$orderId}/settlement", [
            'scenario' => 'owner_charge',
            'charge_account_user_id' => $owner->id,
            'approver_id' => $this->managerUser->id,
            'approver_pin' => '2468',
            'notes' => 'اعتماد مالك',
        ])
            ->assertOk()
            ->assertJsonPath('data.settlement.covered_amount', '85.00')
            ->assertJsonPath('data.settlement.remaining_payable_amount', '0.00');

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.settlement.charge_account_user.id', $owner->id)
            ->assertJsonPath('data.settlement.covered_amount', '85.00');
    }

    public function test_cashier_can_record_cash_movements_and_close_drawer_and_shift_end_to_end(): void
    {
        $shiftId = $this->openShiftAsAdmin();
        $drawerId = $this->openDrawerAsCashier($shiftId, 100);

        Sanctum::actingAs($this->cashierUser->fresh());

        $orderId = $this->postJson('/api/orders', [
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pos_order_type_id' => $this->defaultOrderType->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/orders/{$orderId}/items", [
            'menu_item_id' => $this->variableItem->id,
            'variant_id' => $this->largeVariant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson("/api/orders/{$orderId}/pay", [
            'payments' => [
                [
                    'method' => PaymentMethod::Cash->value,
                    'amount' => 85,
                ],
            ],
        ])->assertOk();

        $this->getJson('/api/pos/manager-approvers')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->managerUser->id]);

        $this->postJson("/api/drawers/{$drawerId}/cash-in", [
            'amount' => 40,
            'notes' => 'فكة إضافية',
            'approver_id' => $this->managerUser->id,
            'approver_pin' => '2468',
        ])->assertCreated();

        $this->postJson("/api/drawers/{$drawerId}/cash-out", [
            'amount' => 15,
            'notes' => 'سحب مصروف بسيط',
            'approver_id' => $this->managerUser->id,
            'approver_pin' => '2468',
        ])->assertCreated();

        $this->getJson('/api/drawers/active')
            ->assertOk()
            ->assertJsonPath('data.id', $drawerId)
            ->assertJsonPath('data.close_reconciliation', null);

        $actualCash = 210.0;

        $previewResponse = $this->postJson("/api/drawers/{$drawerId}/close-preview", [
            'actual_cash' => $actualCash,
        ])->assertOk()
            ->assertJsonPath('data.matches_expected', true);

        $this->assertSame($actualCash, (float) $previewResponse->json('data.expected_cash'));

        $previewToken = $previewResponse->json('data.preview_token');

        $this->postJson("/api/drawers/{$drawerId}/close", [
            'actual_cash' => $actualCash,
            'preview_token' => $previewToken,
            'notes' => 'إغلاق آخر اليوم',
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.cash_difference', '0.00');

        Sanctum::actingAs($this->adminUser);

        $this->postJson("/api/shifts/{$shiftId}/close", [
            'actual_cash' => $actualCash,
            'notes' => 'إغلاق الوردية',
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.cash_difference', '0.00');

        $this->getJson('/api/shifts/active')
            ->assertOk()
            ->assertJsonPath('data', null);

        Sanctum::actingAs($this->cashierUser->fresh());

        $this->getJson('/api/pos/status')
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.block_reason', 'لا توجد جلسة درج مفتوحة لهذا الكاشير');
    }

    public function test_cashier_can_create_external_order_from_pos_context(): void
    {
        $shiftId = $this->openShiftAsAdmin();
        $drawerId = $this->openDrawerAsCashier($shiftId, 100);

        Sanctum::actingAs($this->cashierUser);

        $orderId = $this->postJson('/api/orders/external', [
            'source' => OrderSource::Talabat->value,
            'drawer_session_id' => $drawerId,
            'customer_name' => 'Talabat Customer',
            'customer_phone' => '01099990003',
            'delivery_address' => 'Nasr City',
            'external_order_id' => 'TAL-12345',
            'external_order_number' => 'TB-5566',
            'notes' => 'External order from platform',
        ])->assertCreated()
            ->assertJsonPath('data.source', OrderSource::Talabat->value)
            ->assertJsonPath('data.type', OrderType::Delivery->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->json('data.id');

        $this->getJson('/api/orders?source=' . OrderSource::Talabat->value)
            ->assertOk()
            ->assertJsonPath('data.0.id', $orderId);

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.external_order_id', 'TAL-12345')
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('data.drawer_session_id', $drawerId);
    }

    private function openShiftAsAdmin(): int
    {
        Sanctum::actingAs($this->adminUser);

        return $this->postJson('/api/shifts/open', [
            'notes' => 'فتح وردية تشغيل POS',
        ])->assertCreated()->json('data.id');
    }

    private function openDrawerAsCashier(int $shiftId, float $openingBalance): int
    {
        Sanctum::actingAs($this->cashierUser);

        return $this->postJson('/api/drawers/open', [
            'shift_id' => $shiftId,
            'pos_device_id' => $this->device->id,
            'opening_balance' => $openingBalance,
        ])->assertCreated()->json('data.id');
    }

    private function grantCashierPermission(string $permissionName, string $displayName): void
    {
        Permission::firstOrCreate(
            ['name' => $permissionName],
            ['display_name' => $displayName, 'group' => 'اختبارات POS']
        );

        $cashierRole = Role::firstWhere('name', 'cashier');
        $cashierRole->givePermissionTo($permissionName);

        $this->cashierUser = $this->cashierUser->fresh();
    }
}
