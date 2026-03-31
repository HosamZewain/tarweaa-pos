<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\InventoryLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryLocationsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_inventory_locations_are_created_and_current_stock_is_backfilled_to_restaurant(): void
    {
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2026_03_31_000000_add_inventory_locations_and_transfers.php',
            '--force' => true,
        ]);

        $item = InventoryItem::create([
            'name' => 'أرز',
            'unit' => 'كجم',
            'unit_cost' => 70,
            'current_stock' => 25.500,
            'minimum_stock' => 5,
            'maximum_stock' => 40,
            'is_active' => true,
        ]);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_03_31_000000_add_inventory_locations_and_transfers.php',
            '--force' => true,
        ]);

        $warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->first();
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $this->assertNotNull($warehouse);
        $this->assertSame('warehouse', $warehouse->type);
        $this->assertSame('restaurant', $restaurant->type);
        $this->assertTrue($restaurant->is_default_purchase_destination);
        $this->assertTrue($restaurant->is_default_recipe_deduction_location);

        $stock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $restaurant->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertSame('25.500', $stock->current_stock);
        $this->assertSame('70.00', $stock->unit_cost);
        $this->assertSame('5.000', $stock->minimum_stock);
        $this->assertSame('40.000', $stock->maximum_stock);
    }

    public function test_purchase_destination_location_defaults_to_restaurant_foundation_record(): void
    {
        $supplier = Supplier::create([
            'name' => 'مورد تجريبي',
            'is_active' => true,
        ]);

        $restaurant = app(InventoryLocationService::class)->defaultPurchaseDestination();

        $purchase = Purchase::create([
            'purchase_number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'destination_location_id' => $restaurant?->id,
            'status' => 'draft',
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 0,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
        ]);

        $this->assertNotNull($restaurant);
        $this->assertSame($restaurant->id, $purchase->destinationLocation?->id);
    }
}
