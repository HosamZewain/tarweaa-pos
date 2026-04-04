<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\DTOs\ProcessPaymentData;
use App\Enums\DrawerSessionStatus;
use App\Enums\InventoryItemType;
use App\Enums\InventoryTransactionType;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
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
use App\Models\ProductionRecipe;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\OrderCreationService;
use App\Services\OrderPaymentService;
use App\Services\ProductionBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBatchFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_batch_consumes_inputs_and_adds_prepared_item_stock_with_batch_cost(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $beans = $this->createStockedItem(
            name: 'فول مجروش',
            unit: 'كجم',
            itemType: InventoryItemType::RawMaterial,
            unitCost: 20,
            quantity: 10,
            location: $restaurant,
            actorId: $actor->id,
        );

        $spices = $this->createStockedItem(
            name: 'بهارات',
            unit: 'كجم',
            itemType: InventoryItemType::RawMaterial,
            unitCost: 50,
            quantity: 5,
            location: $restaurant,
            actorId: $actor->id,
        );

        $prepared = InventoryItem::create([
            'name' => 'معجون فلافل',
            'item_type' => InventoryItemType::PreparedItem,
            'unit' => 'كجم',
            'unit_cost' => 0,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 100,
            'is_active' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $recipe = ProductionRecipe::create([
            'prepared_item_id' => $prepared->id,
            'name' => 'تشغيلة معجون فلافل',
            'output_quantity' => 10,
            'output_unit' => 'كجم',
            'is_active' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $recipe->lines()->create([
            'inventory_item_id' => $beans->id,
            'quantity' => 5,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 1,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $recipe->lines()->create([
            'inventory_item_id' => $spices->id,
            'quantity' => 1,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 2,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $batch = app(ProductionBatchService::class)->produce(
            recipe: $recipe,
            actualOutputQuantity: 12,
            actorId: $actor->id,
            location: $restaurant,
            notes: 'تشغيلة صباحية',
        );

        $beans->refresh();
        $spices->refresh();
        $prepared->refresh();

        $preparedRestaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $prepared->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertSame('5.000', $beans->current_stock);
        $this->assertSame('4.000', $spices->current_stock);
        $this->assertSame('12.000', $prepared->current_stock);
        $this->assertSame('12.000', $preparedRestaurantStock->current_stock);
        $this->assertSame('12.50', $prepared->unit_cost);
        $this->assertSame('12.50', $preparedRestaurantStock->unit_cost);

        $this->assertSame('10.000', $batch->planned_output_quantity);
        $this->assertSame('12.000', $batch->actual_output_quantity);
        $this->assertSame('150.00', $batch->total_input_cost);
        $this->assertSame('12.50', $batch->unit_cost);
        $this->assertSame('2.000', $batch->yield_variance_quantity);
        $this->assertSame('20.00', $batch->yield_variance_percentage);
        $this->assertCount(2, $batch->lines);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $beans->id,
            'inventory_location_id' => $restaurant->id,
            'type' => InventoryTransactionType::ProductionConsumption->value,
            'reference_type' => 'production_batch',
            'reference_id' => $batch->id,
            'quantity' => '-5.000',
        ]);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $prepared->id,
            'inventory_location_id' => $restaurant->id,
            'type' => InventoryTransactionType::ProductionOutput->value,
            'reference_type' => 'production_batch',
            'reference_id' => $batch->id,
            'quantity' => '12.000',
            'unit_cost' => '12.50',
        ]);
    }

    public function test_paid_menu_item_can_consume_prepared_item_without_re_deducting_raw_materials(): void
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier Prepared',
            'is_active' => true,
            'password' => 'password',
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier']
        );
        $cashier->roles()->sync([$cashierRole->id]);

        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $beans = $this->createStockedItem(
            name: 'فول خام',
            unit: 'كجم',
            itemType: InventoryItemType::RawMaterial,
            unitCost: 20,
            quantity: 8,
            location: $restaurant,
            actorId: $cashier->id,
        );

        $prepared = InventoryItem::create([
            'name' => 'معجون فلافل جاهز',
            'item_type' => InventoryItemType::PreparedItem,
            'unit' => 'كجم',
            'unit_cost' => 0,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 50,
            'is_active' => true,
            'created_by' => $cashier->id,
            'updated_by' => $cashier->id,
        ]);

        $recipe = ProductionRecipe::create([
            'prepared_item_id' => $prepared->id,
            'name' => 'دفعة فلافل جاهزة',
            'output_quantity' => 4,
            'output_unit' => 'كجم',
            'is_active' => true,
            'created_by' => $cashier->id,
            'updated_by' => $cashier->id,
        ]);

        $recipe->lines()->create([
            'inventory_item_id' => $beans->id,
            'quantity' => 4,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 1,
            'created_by' => $cashier->id,
            'updated_by' => $cashier->id,
        ]);

        app(ProductionBatchService::class)->produce(
            recipe: $recipe,
            actualOutputQuantity: 4,
            actorId: $cashier->id,
            location: $restaurant,
        );

        $this->assertSame('4.000', $beans->fresh()->current_stock);
        $this->assertSame('20.00', $prepared->fresh()->unit_cost);

        $category = MenuCategory::create([
            'name' => 'سندوتشات',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'سندوتش فلافل',
            'type' => 'simple',
            'base_price' => 40,
            'is_available' => true,
            'is_active' => true,
        ]);

        MenuItemRecipeLine::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $prepared->id,
            'quantity' => 250,
            'unit' => 'جم',
            'unit_conversion_rate' => 0.001,
        ]);

        $this->assertSame(5.0, $menuItem->fresh()->recipeCost());

        $orderContext = $this->createOperationalContext($cashier);

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
                new ProcessPaymentData(method: PaymentMethod::Cash, amount: 80),
            ],
            actorId: $cashier->id,
        );

        $prepared->refresh();
        $beans->refresh();

        $preparedRestaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $prepared->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertSame('3.500', $prepared->current_stock);
        $this->assertSame('3.500', $preparedRestaurantStock->current_stock);
        $this->assertSame('4.000', $beans->current_stock);
        $this->assertNotNull($orderItem->fresh()->stock_deducted_at);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $prepared->id,
            'inventory_location_id' => $restaurant->id,
            'type' => InventoryTransactionType::SaleDeduction->value,
            'reference_type' => 'order_item',
            'reference_id' => $orderItem->id,
            'quantity' => '-0.500',
        ]);

        $this->assertDatabaseMissing('inventory_transactions', [
            'inventory_item_id' => $beans->id,
            'type' => InventoryTransactionType::SaleDeduction->value,
            'reference_type' => 'order_item',
            'reference_id' => $orderItem->id,
        ]);

        unset($orderContext);
    }

    private function createStockedItem(
        string $name,
        string $unit,
        InventoryItemType $itemType,
        float $unitCost,
        float $quantity,
        InventoryLocation $location,
        int $actorId,
    ): InventoryItem {
        $item = InventoryItem::create([
            'name' => $name,
            'item_type' => $itemType,
            'unit' => $unit,
            'unit_cost' => $unitCost,
            'current_stock' => $quantity,
            'minimum_stock' => 0,
            'maximum_stock' => 100,
            'is_active' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $location->id,
            'current_stock' => $quantity,
            'minimum_stock' => 0,
            'maximum_stock' => 100,
            'unit_cost' => $unitCost,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return $item;
    }

    private function createOperationalContext(User $cashier): array
    {
        $shift = Shift::create([
            'shift_number' => 'SHIFT-PRODUCTION-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS-Prepared',
            'identifier' => 'POS-PREPARED-001',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-PRODUCTION-001',
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

        return compact('shift', 'device', 'drawer');
    }
}
