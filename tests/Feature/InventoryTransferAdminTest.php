<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Filament\Resources\InventoryTransferResource\Pages\CreateInventoryTransfer;
use App\Filament\Resources\InventoryTransferResource\Pages\EditInventoryTransfer;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryTransferAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private InventoryLocation $warehouse;

    private InventoryLocation $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->admin = User::where('email', 'admin@pos.com')->firstOrFail();
        $this->warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();
        $this->restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
    }

    public function test_admin_can_create_inventory_transfer_from_warehouse_to_restaurant(): void
    {
        $item = InventoryItem::create([
            'name' => 'اختبار تحويل',
            'unit' => 'قطعة',
            'unit_cost' => 12,
            'current_stock' => 10,
            'minimum_stock' => 1,
            'maximum_stock' => 100,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $this->warehouse->id,
            'current_stock' => 10,
            'minimum_stock' => 1,
            'maximum_stock' => 100,
            'unit_cost' => 12,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CreateInventoryTransfer::class)
            ->fillForm([
                'source_location_id' => $this->warehouse->id,
                'destination_location_id' => $this->restaurant->id,
                'notes' => 'تحويل اختبار',
                'items' => [
                    [
                        'inventory_item_id' => $item->id,
                        'unit' => 'قطعة',
                        'quantity_sent' => 3,
                        'unit_cost' => 12,
                        'notes' => 'سطر تحويل',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_transfers', [
            'source_location_id' => $this->warehouse->id,
            'destination_location_id' => $this->restaurant->id,
            'status' => 'draft',
            'requested_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('inventory_transfer_items', [
            'inventory_item_id' => $item->id,
            'unit' => 'قطعة',
            'quantity_sent' => '3.000',
            'unit_cost' => '12.00',
        ]);
    }

    public function test_admin_can_send_inventory_transfer_from_edit_page(): void
    {
        $item = $this->createTransferItem('إرسال تحويل', 14, 8);
        [$transfer] = $this->createDraftTransfer($item, 3);

        Livewire::actingAs($this->admin)
            ->test(EditInventoryTransfer::class, ['record' => $transfer->getRouteKey()])
            ->callAction('approve')
            ->callAction('send');

        $transfer->refresh();
        $item->refresh();

        $warehouseStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->warehouse->id)
            ->firstOrFail();

        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->restaurant->id)
            ->first();

        $this->assertSame('sent', $transfer->status);
        $this->assertSame($this->admin->id, $transfer->approved_by);
        $this->assertSame($this->admin->id, $transfer->transferred_by);
        $this->assertSame('8.000', $item->current_stock);
        $this->assertSame('5.000', $warehouseStock->current_stock);
        $this->assertNull($restaurantStock);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $this->warehouse->id,
            'type' => InventoryTransactionType::TransferOut->value,
            'reference_type' => 'inventory_transfer',
            'reference_id' => $transfer->id,
            'quantity' => '-3.000',
        ]);
    }

    public function test_admin_can_receive_inventory_transfer_from_edit_page_with_partial_quantity(): void
    {
        $item = $this->createTransferItem('استلام تحويل', 20, 10);
        [$transfer, $transferItem] = $this->createDraftTransfer($item, 6);

        Livewire::actingAs($this->admin)
            ->test(EditInventoryTransfer::class, ['record' => $transfer->getRouteKey()])
            ->callAction('approve')
            ->callAction('send');

        Livewire::actingAs($this->admin)
            ->test(EditInventoryTransfer::class, ['record' => $transfer->getRouteKey()])
            ->callAction('receive', data: [
                'items' => [
                    [
                        'id' => $transferItem->id,
                        'quantity_received' => 5,
                    ],
                ],
            ]);

        $transfer->refresh();
        $transferItem->refresh();
        $item->refresh();

        $warehouseStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->warehouse->id)
            ->firstOrFail();

        $restaurantStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->restaurant->id)
            ->firstOrFail();

        $transferIn = InventoryTransaction::query()
            ->where('reference_type', 'inventory_transfer')
            ->where('reference_id', $transfer->id)
            ->where('type', InventoryTransactionType::TransferIn)
            ->firstOrFail();

        $this->assertSame('received', $transfer->status);
        $this->assertSame($this->admin->id, $transfer->received_by);
        $this->assertSame('5.000', $transferItem->quantity_received);
        $this->assertSame('10.000', $item->current_stock);
        $this->assertSame('4.000', $warehouseStock->current_stock);
        $this->assertSame('5.000', $restaurantStock->current_stock);
        $this->assertSame('0.000', $transferIn->quantity_before);
        $this->assertSame('5.000', $transferIn->quantity_after);
    }

    private function createTransferItem(string $name, float $unitCost, float $warehouseStock): InventoryItem
    {
        $item = InventoryItem::create([
            'name' => $name,
            'unit' => 'قطعة',
            'unit_cost' => $unitCost,
            'current_stock' => $warehouseStock,
            'minimum_stock' => 1,
            'maximum_stock' => 100,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $this->warehouse->id,
            'current_stock' => $warehouseStock,
            'minimum_stock' => 1,
            'maximum_stock' => 100,
            'unit_cost' => $unitCost,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        return $item;
    }

    /**
     * @return array{0: InventoryTransfer, 1: InventoryTransferItem}
     */
    private function createDraftTransfer(InventoryItem $item, float $quantitySent): array
    {
        $transfer = InventoryTransfer::create([
            'source_location_id' => $this->warehouse->id,
            'destination_location_id' => $this->restaurant->id,
            'status' => 'draft',
            'requested_by' => $this->admin->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'notes' => 'تحويل لاختبار شاشة الإدارة',
        ]);

        $transferItem = $transfer->items()->create([
            'inventory_item_id' => $item->id,
            'unit' => 'قطعة',
            'quantity_sent' => $quantitySent,
            'quantity_received' => 0,
            'unit_cost' => $item->unit_cost,
            'notes' => 'سطر اختبار',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        return [$transfer, $transferItem];
    }
}
