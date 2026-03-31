<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransaction;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLocationReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_service_returns_location_based_stock_valuation_purchases_and_transfers(): void
    {
        $warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $item = InventoryItem::create([
            'name' => 'دقيق',
            'unit' => 'كجم',
            'unit_cost' => 12,
            'current_stock' => 16,
            'minimum_stock' => 3,
            'maximum_stock' => 30,
            'is_active' => true,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $restaurant->id,
            'current_stock' => 5,
            'minimum_stock' => 6,
            'maximum_stock' => 30,
            'unit_cost' => 14,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $warehouse->id,
            'current_stock' => 10,
            'minimum_stock' => 2,
            'maximum_stock' => 20,
            'unit_cost' => 11,
        ]);

        $supplier = Supplier::create([
            'name' => 'مورد تقارير مواقع',
            'is_active' => true,
        ]);

        Purchase::create([
            'supplier_id' => $supplier->id,
            'destination_location_id' => $warehouse->id,
            'status' => 'received',
            'subtotal' => 150,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 150,
            'paid_amount' => 150,
            'payment_status' => 'paid',
        ]);

        $requester = User::factory()->create(['is_active' => true]);

        $transfer = InventoryTransfer::create([
            'source_location_id' => $warehouse->id,
            'destination_location_id' => $restaurant->id,
            'requested_by' => $requester->id,
            'status' => 'received',
            'sent_at' => now(),
            'received_at' => now(),
        ]);

        $transfer->items()->create([
            'inventory_item_id' => $item->id,
            'unit' => 'كجم',
            'quantity_sent' => 3,
            'quantity_received' => 3,
            'unit_cost' => 11,
        ]);

        InventoryTransaction::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $warehouse->id,
            'type' => 'purchase',
            'quantity' => 4,
            'quantity_before' => 6,
            'quantity_after' => 10,
            'unit_cost' => 11,
            'total_cost' => 44,
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'performed_by' => $requester->id,
            'created_by' => $requester->id,
            'updated_by' => $requester->id,
        ]);

        $service = app(ReportService::class);

        $valuation = $service->getInventoryValuationByLocation();
        $stockRows = $service->getStockByLocation();
        $lowStock = $service->getLowStockByLocation();
        $purchases = $service->getPurchasesByLocation();
        $received = $service->getReceivedStockByLocation();
        $reconciliation = $service->getStockReconciliation();
        $transfers = $service->getInventoryTransfersReport();

        $this->assertCount(2, $valuation['rows']);
        $this->assertSame(180.0, $valuation['summary']['total_value']);
        $this->assertCount(2, $stockRows);
        $this->assertCount(1, $lowStock);
        $this->assertSame('المطعم', $lowStock->first()['location_name']);
        $this->assertSame(1, $purchases['summary']['purchases_count']);
        $this->assertSame('المخزن الرئيسي', $purchases['by_location']->first()['location_name']);
        $this->assertSame(1, $received['summary']['transactions_count']);
        $this->assertSame(4.0, $received['summary']['received_quantity']);
        $this->assertSame(44.0, $received['summary']['received_value']);
        $this->assertSame(1, $reconciliation['summary']['mismatched_items']);
        $this->assertSame(16.0, $reconciliation['summary']['global_total_stock']);
        $this->assertSame(15.0, $reconciliation['summary']['locations_total_stock']);
        $this->assertSame(1, $transfers['summary']['transfers_count']);
        $this->assertSame(3.0, $transfers['summary']['total_quantity_sent']);
    }
}
