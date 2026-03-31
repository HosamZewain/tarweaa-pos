<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseResource\Pages\CreatePurchase;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseAdminCreationTest extends TestCase
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

    public function test_admin_can_create_purchase_without_explicit_subtotal(): void
    {
        $supplier = Supplier::create([
            'name' => 'Purchase Creation Supplier',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $location = InventoryLocation::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        Livewire::actingAs($this->adminUser)
            ->test(CreatePurchase::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'destination_location_id' => $location->id,
                'status' => 'ordered',
                'invoice_date' => now()->toDateString(),
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 1500,
                'paid_amount' => 1500,
                'payment_status' => 'paid',
                'payment_method' => 'cash',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchases', [
            'supplier_id' => $supplier->id,
            'destination_location_id' => $location->id,
            'subtotal' => 0,
            'total' => 1500,
            'paid_amount' => 1500,
        ]);
    }

    public function test_admin_can_create_purchase_with_inline_items_without_receiving_stock(): void
    {
        $supplier = Supplier::create([
            'name' => 'Inline Purchase Supplier',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $location = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();

        $item = InventoryItem::create([
            'name' => 'صلصة',
            'unit' => 'كجم',
            'unit_cost' => 15,
            'current_stock' => 2,
            'minimum_stock' => 1,
            'maximum_stock' => 20,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(CreatePurchase::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'destination_location_id' => $location->id,
                'status' => 'ordered',
                'invoice_date' => now()->toDateString(),
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 80,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'items_payload' => [
                    [
                        'inventory_item_id' => $item->id,
                        'unit' => 'كجم',
                        'unit_price' => 20,
                        'quantity_ordered' => 4,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $purchase = \App\Models\Purchase::query()->latest('id')->firstOrFail();
        $item->refresh();

        $this->assertSame('ordered', $purchase->status);
        $this->assertSame('80.00', $purchase->subtotal);
        $this->assertSame('80.00', $purchase->total);
        $this->assertCount(1, $purchase->items);
        $this->assertSame('0.000', $purchase->items->first()->quantity_received);
        $this->assertSame('2.000', $item->current_stock);
    }

    public function test_admin_can_create_purchase_as_received_and_it_updates_inventory_immediately(): void
    {
        $supplier = Supplier::create([
            'name' => 'Immediate Receive Supplier',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $location = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();

        $item = InventoryItem::create([
            'name' => 'مكرونة',
            'unit' => 'كجم',
            'unit_cost' => 10,
            'current_stock' => 5,
            'minimum_stock' => 1,
            'maximum_stock' => 20,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(CreatePurchase::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'destination_location_id' => $location->id,
                'status' => 'received',
                'invoice_date' => now()->toDateString(),
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 60,
                'paid_amount' => 60,
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'items_payload' => [
                    [
                        'inventory_item_id' => $item->id,
                        'unit' => 'كجم',
                        'unit_price' => 12,
                        'quantity_ordered' => 5,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $purchase = \App\Models\Purchase::query()->latest('id')->firstOrFail();
        $item->refresh();

        $locationStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $location->id)
            ->firstOrFail();

        $this->assertSame('received', $purchase->status);
        $this->assertNotNull($purchase->received_at);
        $this->assertSame('10.000', $item->current_stock);
        $this->assertSame('5.000', $locationStock->current_stock);
        $this->assertSame('5.000', $purchase->items()->firstOrFail()->quantity_received);
    }
}
