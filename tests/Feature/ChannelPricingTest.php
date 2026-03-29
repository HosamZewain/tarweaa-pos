<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\Enums\ChannelPricingRuleType;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemChannelPrice;
use App\Models\MenuItemVariant;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChannelPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_pos_menu_applies_default_channel_pricing_rule(): void
    {
        $cashier = $this->createCashier();
        $talabat = $this->createOrderType(
            name: 'طلبات',
            pricingRuleType: ChannelPricingRuleType::PercentageAdjustment,
            pricingRuleValue: 20,
        );
        $item = $this->createMenuItem('فول', 100);

        Sanctum::actingAs($cashier);

        $response = $this->getJson('/api/pos/menu?pos_order_type_id=' . $talabat->id)
            ->assertOk()
            ->json('data');

        $returnedItem = collect($response)
            ->flatMap(fn (array $category) => $category['menu_items'] ?? [])
            ->firstWhere('id', $item->id);

        $this->assertNotNull($returnedItem);
        $this->assertSame(100.0, (float) $returnedItem['base_price']);
        $this->assertSame(120.0, (float) $returnedItem['price']);
    }

    public function test_item_specific_channel_override_takes_precedence_over_default_rule(): void
    {
        $cashier = $this->createCashier();
        $talabat = $this->createOrderType(
            name: 'طلبات',
            pricingRuleType: ChannelPricingRuleType::PercentageAdjustment,
            pricingRuleValue: 20,
        );
        $item = $this->createMenuItem('بطاطس', 100);

        MenuItemChannelPrice::create([
            'menu_item_id' => $item->id,
            'pos_order_type_id' => $talabat->id,
            'price' => 145,
        ]);

        Sanctum::actingAs($cashier);

        $response = $this->getJson('/api/pos/menu?pos_order_type_id=' . $talabat->id)
            ->assertOk()
            ->json('data');

        $returnedItem = collect($response)
            ->flatMap(fn (array $category) => $category['menu_items'] ?? [])
            ->firstWhere('id', $item->id);

        $this->assertNotNull($returnedItem);
        $this->assertSame(145.0, (float) $returnedItem['price']);
    }

    public function test_order_item_snapshot_uses_resolved_channel_price(): void
    {
        $cashier = $this->createCashier();
        $talabat = $this->createOrderType(
            name: 'طلبات',
            pricingRuleType: ChannelPricingRuleType::PercentageAdjustment,
            pricingRuleValue: 20,
        );
        $item = $this->createMenuItem('فلافل', 100);

        $order = $this->createOrderForCashier($cashier, $talabat);

        $orderItem = app(OrderCreationService::class)->addItem(
            order: $order,
            data: AddOrderItemData::fromArray([
                'menu_item_id' => $item->id,
                'quantity' => 1,
            ]),
            actorId: $cashier->id,
        );

        $this->assertSame('120.00', $orderItem->fresh()->unit_price);
        $this->assertSame('120.00', $orderItem->fresh()->total);
    }

    public function test_variant_price_remains_the_base_for_channel_rule_when_no_override_exists(): void
    {
        $cashier = $this->createCashier();
        $delivery = $this->createOrderType(
            name: 'ديليفري',
            pricingRuleType: ChannelPricingRuleType::PercentageAdjustment,
            pricingRuleValue: 10,
        );
        $item = $this->createMenuItem('بيتزا', 90, type: 'variable');
        $variant = MenuItemVariant::create([
            'menu_item_id' => $item->id,
            'name' => 'كبير',
            'price' => 120,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $order = $this->createOrderForCashier($cashier, $delivery);

        $orderItem = app(OrderCreationService::class)->addItem(
            order: $order,
            data: AddOrderItemData::fromArray([
                'menu_item_id' => $item->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]),
            actorId: $cashier->id,
        );

        $this->assertSame('132.00', $orderItem->fresh()->unit_price);
    }

    private function createCashier(): User
    {
        $cashier = User::factory()->create([
            'name' => 'Channel Cashier',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier', 'is_active' => true],
        );

        $cashier->roles()->syncWithoutDetaching([$role->id]);

        return $cashier->fresh(['roles.permissions']);
    }

    private function createOrderType(
        string $name,
        ChannelPricingRuleType $pricingRuleType = ChannelPricingRuleType::BasePrice,
        float $pricingRuleValue = 0,
    ): PosOrderType {
        return PosOrderType::create([
            'name' => $name,
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'pricing_rule_type' => $pricingRuleType->value,
            'pricing_rule_value' => $pricingRuleValue,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }

    private function createMenuItem(string $name, float $price, string $type = 'simple'): MenuItem
    {
        $category = MenuCategory::firstOrCreate([
            'name' => 'قناة التسعير',
        ], [
            'is_active' => true,
        ]);

        return MenuItem::create([
            'category_id' => $category->id,
            'name' => $name,
            'type' => $type,
            'base_price' => $price,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    private function createOrderForCashier(User $cashier, PosOrderType $posOrderType): \App\Models\Order
    {
        $shift = Shift::create([
            'shift_number' => 'SHIFT-CHANNEL-001',
            'status' => 'open',
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'Pricing POS',
            'identifier' => 'PRICING-POS-01',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRAWER-CHANNEL-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now()->subHour(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        return app(OrderCreationService::class)->create(
            $cashier,
            CreateOrderData::fromArray([
                'pos_order_type_id' => $posOrderType->id,
                'type' => OrderType::Takeaway->value,
                'source' => OrderSource::Pos->value,
            ]),
        );
    }
}
