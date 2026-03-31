<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\InventoryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLocationOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_receive_updates_selected_location_and_global_stock(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'مورد تحويلات',
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $item = InventoryItem::create([
            'name' => 'أرز',
            'unit' => 'كجم',
            'unit_cost' => 5,
            'current_stock' => 10,
            'minimum_stock' => 1,
            'maximum_stock' => 100,
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $restaurant->id,
            'current_stock' => 10,
            'minimum_stock' => 1,
            'maximum_stock' => 100,
            'unit_cost' => 5,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'destination_location_id' => $warehouse->id,
            'status' => 'ordered',
            'subtotal' => 40,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 40,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $line = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'inventory_item_id' => $item->id,
            'unit' => 'كجم',
            'unit_price' => 8,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'total' => 40,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $line->receive(5);

        $item->refresh();
        $purchase->refresh();

        $this->assertSame('15.000', $item->current_stock);
        $this->assertSame('6.00', $item->unit_cost);
        $this->assertSame('received', $purchase->status);
        $this->assertNotNull($purchase->received_at);

        $warehouseStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $warehouse->id)
            ->firstOrFail();

        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertSame('5.000', $warehouseStock->current_stock);
        $this->assertSame('8.00', $warehouseStock->unit_cost);
        $this->assertSame('10.000', $restaurantStock->current_stock);

        $transaction = InventoryTransaction::query()
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->firstOrFail();

        $this->assertSame(InventoryTransactionType::Purchase, $transaction->type);
        $this->assertSame($warehouse->id, $transaction->inventory_location_id);
        $this->assertSame('5.000', $transaction->quantity);
        $this->assertSame('0.000', $transaction->quantity_before);
        $this->assertSame('5.000', $transaction->quantity_after);
    }

    public function test_transfer_send_and_receive_updates_location_balances_without_changing_global_stock(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $item = InventoryItem::create([
            'name' => 'زيت',
            'unit' => 'لتر',
            'unit_cost' => 10,
            'current_stock' => 50,
            'minimum_stock' => 1,
            'maximum_stock' => 100,
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $restaurant->id,
            'current_stock' => 30,
            'minimum_stock' => 1,
            'maximum_stock' => 100,
            'unit_cost' => 10,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $warehouse->id,
            'current_stock' => 20,
            'minimum_stock' => 0,
            'maximum_stock' => 100,
            'unit_cost' => 10,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $transfer = InventoryTransfer::create([
            'source_location_id' => $warehouse->id,
            'destination_location_id' => $restaurant->id,
            'status' => 'draft',
            'notes' => 'تحويل تجريبي',
        ]);

        $transfer->items()->create([
            'inventory_item_id' => $item->id,
            'unit' => 'لتر',
            'quantity_sent' => 5,
            'quantity_received' => 0,
            'unit_cost' => 10,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $service = app(InventoryTransferService::class);
        $service->approve($transfer, $user->id);
        $sentTransfer = $service->send($transfer, $user->id);

        $item->refresh();
        $warehouseStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $warehouse->id)
            ->firstOrFail();
        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertSame('50.000', $item->current_stock);
        $this->assertSame('15.000', $warehouseStock->current_stock);
        $this->assertSame('30.000', $restaurantStock->current_stock);
        $this->assertSame('sent', $sentTransfer->status);
        $this->assertSame($user->id, $sentTransfer->transferred_by);

        $transferOut = InventoryTransaction::query()
            ->where('reference_type', 'inventory_transfer')
            ->where('reference_id', $transfer->id)
            ->where('type', InventoryTransactionType::TransferOut)
            ->firstOrFail();

        $this->assertSame($warehouse->id, $transferOut->inventory_location_id);
        $this->assertSame('-5.000', $transferOut->quantity);
        $this->assertSame('20.000', $transferOut->quantity_before);
        $this->assertSame('15.000', $transferOut->quantity_after);

        $receivedTransfer = $service->receive($transfer, $user->id);

        $item->refresh();
        $warehouseStock->refresh();
        $restaurantStock->refresh();

        $this->assertSame('50.000', $item->current_stock);
        $this->assertSame('15.000', $warehouseStock->current_stock);
        $this->assertSame('35.000', $restaurantStock->current_stock);
        $this->assertSame('received', $receivedTransfer->status);
        $this->assertSame($user->id, $receivedTransfer->received_by);

        $transferIn = InventoryTransaction::query()
            ->where('reference_type', 'inventory_transfer')
            ->where('reference_id', $transfer->id)
            ->where('type', InventoryTransactionType::TransferIn)
            ->firstOrFail();

        $this->assertSame($restaurant->id, $transferIn->inventory_location_id);
        $this->assertSame('5.000', $transferIn->quantity);
        $this->assertSame('30.000', $transferIn->quantity_before);
        $this->assertSame('35.000', $transferIn->quantity_after);
    }

    public function test_adjust_location_stock_updates_location_and_global_balances_together(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $item = InventoryItem::create([
            'name' => 'سمن',
            'unit' => 'كجم',
            'unit_cost' => 20,
            'current_stock' => 12,
            'minimum_stock' => 2,
            'maximum_stock' => 30,
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $restaurant->id,
            'current_stock' => 7,
            'minimum_stock' => 2,
            'maximum_stock' => 20,
            'unit_cost' => 20,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $transaction = app(InventoryService::class)->adjustLocationTo(
            item: $item,
            location: $restaurant,
            newQuantity: 9,
            actorId: $user->id,
            notes: 'جرد موقع المطعم',
        );

        $item->refresh();
        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertSame('14.000', $item->current_stock);
        $this->assertSame('9.000', $restaurantStock->current_stock);
        $this->assertSame(InventoryTransactionType::Adjustment, $transaction->type);
        $this->assertSame($restaurant->id, $transaction->inventory_location_id);
        $this->assertSame('2.000', $transaction->quantity);
        $this->assertSame('7.000', $transaction->quantity_before);
        $this->assertSame('9.000', $transaction->quantity_after);
    }
}
