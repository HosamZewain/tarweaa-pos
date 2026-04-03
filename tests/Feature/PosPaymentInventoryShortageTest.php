<?php

namespace Tests\Feature;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\Enums\DrawerSessionStatus;
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
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosPaymentInventoryShortageTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_allows_negative_recipe_deduction_in_default_recipe_location(): void
    {
        $this->artisan('db:seed');

        $cashier = User::factory()->create([
            'name' => 'Cashier Inventory Shortage',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstWhere('name', 'cashier');
        $cashier->roles()->sync([$cashierRole->id]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-STOCK-ERR-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $cashier->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Stock Error',
            'identifier' => 'POS-STOCK-ERR-001',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-STOCK-ERR-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 0,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $inventoryItem = InventoryItem::create([
            'name' => 'تصنيعه فول',
            'unit' => 'كجم',
            'unit_cost' => 50,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 10,
            'is_active' => true,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'inventory_location_id' => $restaurant->id,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 10,
            'unit_cost' => 50,
        ]);

        $category = MenuCategory::create([
            'name' => 'اختبار المخزون',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'فول',
            'type' => 'simple',
            'base_price' => 30,
            'is_available' => true,
            'is_active' => true,
        ]);

        MenuItemRecipeLine::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 0.085,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
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

        Sanctum::actingAs($cashier);

        $this->postJson("/api/orders/{$order->id}/pay", [
            'payments' => [
                [
                    'method' => PaymentMethod::Cash->value,
                    'amount' => 30,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $inventoryItem->refresh();
        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertDatabaseCount('order_payments', 1);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'paid_amount' => '30.00',
        ]);
        $this->assertSame('-0.085', $inventoryItem->current_stock);
        $this->assertSame('-0.085', $restaurantStock->current_stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'inventory_location_id' => $restaurant->id,
            'type' => InventoryTransactionType::SaleDeduction->value,
            'reference_type' => 'order_item',
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'inventory_location_id' => $restaurant->id,
            'type' => InventoryTransactionType::SaleDeduction->value,
            'quantity_after' => '-0.085',
        ]);
        $this->assertStringContainsString(
            'خصم بيع بالسالب مسموح',
            (string) $inventoryItem->transactions()->latest('id')->value('notes'),
        );
    }

    public function test_non_sale_deduction_still_rejects_negative_stock(): void
    {
        $this->artisan('db:seed');

        $actor = User::factory()->create(['is_active' => true]);
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $inventoryItem = InventoryItem::create([
            'name' => 'زيت خام',
            'unit' => 'لتر',
            'unit_cost' => 25,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 10,
            'is_active' => true,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'inventory_location_id' => $restaurant->id,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 10,
            'unit_cost' => 25,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('مخزون الموقع غير كافٍ');

        app(InventoryService::class)->deductStock(
            item: $inventoryItem,
            quantity: 1,
            actorId: $actor->id,
            type: InventoryTransactionType::TransferOut,
            location: $restaurant,
        );
    }

    public function test_sale_deduction_outside_default_recipe_location_still_rejects_negative_stock(): void
    {
        $this->artisan('db:seed');

        $actor = User::factory()->create(['is_active' => true]);
        $warehouse = InventoryLocation::query()->where('type', 'warehouse')->firstOrFail();

        $inventoryItem = InventoryItem::create([
            'name' => 'خبز',
            'unit' => 'قطعة',
            'unit_cost' => 2,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 100,
            'is_active' => true,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'inventory_location_id' => $warehouse->id,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 100,
            'unit_cost' => 2,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('مخزون الموقع غير كافٍ');

        app(InventoryService::class)->deductStock(
            item: $inventoryItem,
            quantity: 1,
            actorId: $actor->id,
            type: InventoryTransactionType::SaleDeduction,
            location: $warehouse,
        );
    }
}
