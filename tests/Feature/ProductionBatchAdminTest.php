<?php

namespace Tests\Feature;

use App\Enums\InventoryItemType;
use App\Enums\InventoryTransactionType;
use App\Filament\Resources\ProductionBatchResource\Pages\CreateProductionBatch;
use App\Filament\Resources\ProductionBatchResource\Pages\ViewProductionBatch;
use App\Filament\Resources\ProductionRecipeResource\Pages\CreateProductionRecipe;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\InventoryTransaction;
use App\Models\ProductionBatch;
use App\Models\ProductionRecipe;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryLocationService;
use App\Services\InventoryService;
use App\Services\ProductionBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionBatchAdminTest extends TestCase
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
                'pin' => '1234',
                'is_active' => true,
            ]);

        if (blank($this->adminUser->pin)) {
            $this->adminUser->forceFill(['pin' => '1234'])->save();
        }

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator'],
        );

        $this->adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
    }

    public function test_admin_can_create_production_recipe_for_prepared_item(): void
    {
        $preparedItem = InventoryItem::create([
            'name' => 'طحينة مُحضّرة',
            'item_type' => InventoryItemType::PreparedItem,
            'unit' => 'كجم',
            'unit_cost' => 0,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'maximum_stock' => 100,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $rawTahini = $this->createInventoryItem('طحينة خام', InventoryItemType::RawMaterial, 60, 10);
        $oil = $this->createInventoryItem('زيت', InventoryItemType::RawMaterial, 25, 10);

        Livewire::actingAs($this->adminUser)
            ->test(CreateProductionRecipe::class)
            ->fillForm([
                'prepared_item_id' => $preparedItem->id,
                'name' => 'وصفة إنتاج الطحينة',
                'output_quantity' => 8,
                'output_unit' => 'كجم',
                'is_active' => true,
                'lines' => [
                    [
                        'inventory_item_id' => $rawTahini->id,
                        'quantity' => 6,
                        'unit' => 'كجم',
                        'unit_conversion_rate' => 1,
                        'sort_order' => 1,
                    ],
                    [
                        'inventory_item_id' => $oil->id,
                        'quantity' => 2,
                        'unit' => 'كجم',
                        'unit_conversion_rate' => 1,
                        'sort_order' => 2,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $recipe = ProductionRecipe::query()->where('prepared_item_id', $preparedItem->id)->firstOrFail();

        $this->assertSame('8.000', $recipe->output_quantity);
        $this->assertCount(2, $recipe->lines);
        $this->assertDatabaseHas('production_recipe_lines', [
            'production_recipe_id' => $recipe->id,
            'inventory_item_id' => $rawTahini->id,
            'quantity' => '6.000',
        ]);
    }

    public function test_admin_can_create_production_batch_from_recipe_and_update_stock(): void
    {
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $beans = $this->createInventoryItem('فول تجهيز', InventoryItemType::RawMaterial, 20, 10, $restaurant);
        $spices = $this->createInventoryItem('بهارات تجهيز', InventoryItemType::RawMaterial, 40, 5, $restaurant);
        $preparedItem = $this->createInventoryItem('عجينة فلافل', InventoryItemType::PreparedItem, 0, 0);

        $recipe = ProductionRecipe::create([
            'prepared_item_id' => $preparedItem->id,
            'name' => 'دفعة فلافل أساسية',
            'output_quantity' => 6,
            'output_unit' => 'كجم',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $beanLine = $recipe->lines()->create([
            'inventory_item_id' => $beans->id,
            'quantity' => 4,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 1,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $spiceLine = $recipe->lines()->create([
            'inventory_item_id' => $spices->id,
            'quantity' => 1,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 2,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(CreateProductionBatch::class)
            ->fillForm([
                'production_recipe_id' => $recipe->id,
                'inventory_location_id' => $restaurant->id,
                'actual_output_quantity' => 7,
                'waste_quantity' => 1,
                'notes' => 'دفعة مطبخ مسائية',
                'waste_notes' => 'هالك بسيط أثناء التحضير',
                'approver_id' => $this->adminUser->id,
                'approver_pin' => '1234',
                'input_quantities_payload' => [
                    [
                        'production_recipe_line_id' => $beanLine->id,
                        'actual_quantity' => 4.5,
                    ],
                    [
                        'production_recipe_line_id' => $spiceLine->id,
                        'actual_quantity' => 1.2,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $batch = ProductionBatch::query()->latest('id')->firstOrFail();
        $preparedItem->refresh();
        $beans->refresh();
        $spices->refresh();

        $preparedLocationStock = InventoryLocationStock::query()
            ->where('inventory_item_id', $preparedItem->id)
            ->where('inventory_location_id', $restaurant->id)
            ->firstOrFail();

        $this->assertSame('5.500', $beans->current_stock);
        $this->assertSame('3.800', $spices->current_stock);
        $this->assertSame('7.000', $preparedItem->current_stock);
        $this->assertSame('7.000', $preparedLocationStock->current_stock);
        $this->assertSame('19.71', $preparedItem->unit_cost);
        $this->assertSame('19.71', $preparedLocationStock->unit_cost);
        $this->assertSame('138.00', $batch->total_input_cost);
        $this->assertSame('19.71', $batch->unit_cost);
        $this->assertSame('1.000', $batch->waste_quantity);
        $this->assertSame($this->adminUser->id, $batch->approved_by);
        $this->assertNotNull($batch->approved_at);
        $this->assertCount(2, $batch->lines);
    }

    public function test_admin_can_void_production_batch_and_restore_stock(): void
    {
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
        $beans = $this->createInventoryItem('فول عكس', InventoryItemType::RawMaterial, 20, 10, $restaurant);
        $preparedItem = $this->createInventoryItem('عجينة عكس', InventoryItemType::PreparedItem, 0, 0);

        $recipe = ProductionRecipe::create([
            'prepared_item_id' => $preparedItem->id,
            'name' => 'دفعة عكس',
            'output_quantity' => 4,
            'output_unit' => 'كجم',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $recipe->lines()->create([
            'inventory_item_id' => $beans->id,
            'quantity' => 4,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 1,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $batch = app(ProductionBatchService::class)->produce(
            recipe: $recipe,
            actualOutputQuantity: 4,
            actorId: $this->adminUser->id,
            location: $restaurant,
            approvedBy: $this->adminUser->id,
        );

        Livewire::actingAs($this->adminUser)
            ->test(ViewProductionBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('voidBatch', data: [
                'reason' => 'تم تسجيل الدفعة بالخطأ',
                'approver_id' => $this->adminUser->id,
                'approver_pin' => '1234',
            ]);

        $batch->refresh();
        $beans->refresh();
        $preparedItem->refresh();

        $this->assertTrue($batch->isCancelled());
        $this->assertSame('10.000', $beans->current_stock);
        $this->assertSame('0.000', $preparedItem->current_stock);
        $this->assertSame($this->adminUser->id, $batch->voided_by);
        $this->assertNotNull($batch->voided_at);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $preparedItem->id,
            'type' => InventoryTransactionType::ProductionVoidOutput->value,
            'reference_type' => 'production_batch_void',
            'reference_id' => $batch->id,
        ]);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $beans->id,
            'type' => InventoryTransactionType::ProductionVoidInputReturn->value,
            'reference_type' => 'production_batch_void',
            'reference_id' => $batch->id,
        ]);
    }

    public function test_cannot_void_production_batch_if_output_was_already_consumed(): void
    {
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
        $beans = $this->createInventoryItem('فول مستهلك', InventoryItemType::RawMaterial, 20, 10, $restaurant);
        $preparedItem = $this->createInventoryItem('عجينة مستهلكة', InventoryItemType::PreparedItem, 0, 0, $restaurant);

        $recipe = ProductionRecipe::create([
            'prepared_item_id' => $preparedItem->id,
            'name' => 'دفعة مستهلكة',
            'output_quantity' => 4,
            'output_unit' => 'كجم',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $recipe->lines()->create([
            'inventory_item_id' => $beans->id,
            'quantity' => 4,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 1,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $batch = app(ProductionBatchService::class)->produce(
            recipe: $recipe,
            actualOutputQuantity: 4,
            actorId: $this->adminUser->id,
            location: $restaurant,
            approvedBy: $this->adminUser->id,
        );

        app(InventoryService::class)->deductStock(
            item: $preparedItem,
            quantity: 1,
            actorId: $this->adminUser->id,
            type: InventoryTransactionType::SaleDeduction,
            refType: 'test',
            refId: 1,
            notes: 'استهلاك تجريبي',
            location: $restaurant,
            updateGlobalStock: true,
        );

        $this->expectExceptionMessage('لا يمكن إلغاء الدفعة لأن الناتج لم يعد متاحًا كاملًا في موقع الإنتاج.');

        app(ProductionBatchService::class)->void(
            batch: $batch,
            actorId: $this->adminUser->id,
            reason: 'محاولة إلغاء غير صالحة',
            approvedBy: $this->adminUser->id,
        );
    }

    public function test_inventory_location_service_prefers_explicit_default_production_location(): void
    {
        $warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $warehouse->update(['is_default_production_location' => true]);
        $restaurant->update(['is_default_production_location' => false]);

        $resolved = app(InventoryLocationService::class)->defaultProductionLocation();

        $this->assertNotNull($resolved);
        $this->assertSame($warehouse->id, $resolved->id);
    }

    private function createInventoryItem(
        string $name,
        InventoryItemType $type,
        float $unitCost,
        float $quantity,
        ?InventoryLocation $location = null,
    ): InventoryItem {
        $item = InventoryItem::create([
            'name' => $name,
            'item_type' => $type,
            'unit' => 'كجم',
            'unit_cost' => $unitCost,
            'current_stock' => $quantity,
            'minimum_stock' => 0,
            'maximum_stock' => 100,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        if ($location) {
            InventoryLocationStock::query()->create([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
                'current_stock' => $quantity,
                'minimum_stock' => 0,
                'maximum_stock' => 100,
                'unit_cost' => $unitCost,
                'created_by' => $this->adminUser->id,
                'updated_by' => $this->adminUser->id,
            ]);
        }

        return $item;
    }
}
