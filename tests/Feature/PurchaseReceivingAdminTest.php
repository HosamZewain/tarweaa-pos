<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseResource\Pages\EditPurchase;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseReceivingAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->first()
            ?? User::factory()->create([
                'email' => 'admin@pos.com',
                'username' => 'admin-user',
                'is_active' => true,
            ]);

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator'],
        );

        $this->adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
    }

    public function test_admin_can_receive_full_purchase_from_purchase_page(): void
    {
        $warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'Bulk Receive Supplier',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $item = InventoryItem::create([
            'name' => 'جبنة',
            'unit' => 'كجم',
            'unit_cost' => 30,
            'current_stock' => 5,
            'minimum_stock' => 1,
            'maximum_stock' => 20,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'destination_location_id' => $warehouse->id,
            'status' => 'ordered',
            'subtotal' => 120,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 120,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'inventory_item_id' => $item->id,
            'unit' => 'كجم',
            'unit_price' => 40,
            'quantity_ordered' => 3,
            'quantity_received' => 0,
            'total' => 120,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(EditPurchase::class, ['record' => $purchase->getRouteKey()])
            ->callAction('receiveFullPurchase');

        $purchase->refresh();
        $item->refresh();

        $warehouseStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $warehouse->id)
            ->firstOrFail();

        $this->assertSame('received', $purchase->status);
        $this->assertNotNull($purchase->received_at);
        $this->assertSame('8.000', $item->current_stock);
        $this->assertSame('3.000', $warehouseStock->current_stock);
        $this->assertTrue($purchase->items()->firstOrFail()->isFullyReceived());
    }
}
