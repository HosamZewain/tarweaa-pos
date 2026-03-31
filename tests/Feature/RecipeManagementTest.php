<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\DTOs\ProcessPaymentData;
use App\Enums\DrawerSessionStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\ShiftStatus;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemRecipeLine;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderCreationService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_cost_and_profit_metrics_follow_inventory_average_cost(): void
    {
        $item = InventoryItem::create([
            'name' => 'لحم',
            'unit' => 'كجم',
            'unit_cost' => 200,
            'current_stock' => 10,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'name' => 'السندوتشات',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'برجر',
            'type' => 'simple',
            'base_price' => 100,
            'is_available' => true,
            'is_active' => true,
        ]);

        MenuItemRecipeLine::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 250,
            'unit' => 'جم',
            'unit_conversion_rate' => 0.001,
        ]);

        $this->assertSame(50.0, $menuItem->fresh()->recipeCost());
        $this->assertSame(50.0, $menuItem->fresh()->foodCostPercentage());
        $this->assertSame(50.0, $menuItem->fresh()->profitMarginAmount());
        $this->assertSame(50.0, $menuItem->fresh()->profitMarginPercentage());

        $actor = User::factory()->create([
            'is_active' => true,
        ]);

        app(InventoryService::class)->addStock(
            item: $item,
            quantity: 10,
            actorId: $actor->id,
            type: InventoryTransactionType::Purchase,
            unitCost: 300,
        );

        $menuItem->refresh();

        $this->assertSame('62.50', $menuItem->cost_price);
        $this->assertSame(62.5, $menuItem->recipeCost());
    }

    public function test_recipe_cost_prefers_restaurant_location_average_cost_when_available(): void
    {
        $item = InventoryItem::create([
            'name' => 'فراخ',
            'unit' => 'كجم',
            'unit_cost' => 200,
            'current_stock' => 10,
            'minimum_stock' => 1,
            'is_active' => true,
        ]);

        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $restaurant->id,
            'current_stock' => 6,
            'minimum_stock' => 1,
            'maximum_stock' => 20,
            'unit_cost' => 120,
        ]);

        $category = MenuCategory::create([
            'name' => 'وجبات',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'شاورما فراخ',
            'type' => 'simple',
            'base_price' => 90,
            'is_available' => true,
            'is_active' => true,
        ]);

        MenuItemRecipeLine::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $item->id,
            'quantity' => 250,
            'unit' => 'جم',
            'unit_conversion_rate' => 0.001,
        ]);

        app(\App\Services\RecipeService::class)->syncMenuItemCostsForInventoryItem($item);

        $this->assertSame(30.0, $menuItem->fresh()->recipeCost());
        $this->assertSame(30.0, $menuItem->fresh()->effectiveCostPrice());
        $this->assertSame('30.00', $menuItem->fresh()->cost_price);
    }

    public function test_paid_order_deducts_recipe_quantities_from_inventory(): void
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier',
            'is_active' => true,
            'password' => 'password',
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier']
        );
        $cashier->roles()->attach($cashierRole->id);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-RECIPE-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS-Recipe',
            'identifier' => 'POS-RECIPE-001',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-RECIPE-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 500,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'جبنة',
            'unit' => 'كجم',
            'unit_cost' => 120,
            'current_stock' => 5,
            'minimum_stock' => 0,
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'name' => 'بيتزا',
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
                new ProcessPaymentData(method: \App\Enums\PaymentMethod::Cash, amount: 360),
            ],
            actorId: $cashier->id,
        );

        $inventoryItem->refresh();
        $orderItem->refresh();
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertSame('4.600', $inventoryItem->current_stock);
        $this->assertSame('4.600', $restaurantStock->current_stock);
        $this->assertNotNull($orderItem->stock_deducted_at);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'inventory_location_id' => $restaurant->id,
            'type' => InventoryTransactionType::SaleDeduction->value,
            'reference_type' => 'order_item',
            'reference_id' => $orderItem->id,
            'quantity' => '-0.400',
        ]);
    }
}
