<?php

namespace Tests\Feature;

use App\Enums\InventoryItemType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\ProductionRecipe;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductionBatchService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReportingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $managerUser;
    protected Role $managerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->first()
            ?? User::factory()->create([
                'email' => 'admin@pos.com',
                'is_active' => true,
            ]);

        $adminRole = Role::firstWhere('name', 'admin');
        $this->adminUser->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->managerUser = User::factory()->create([
            'name' => 'Production Manager',
            'email' => 'production.manager@example.com',
            'username' => 'production-manager',
            'is_active' => true,
        ]);

        $this->managerRole = Role::firstWhere('name', 'manager');
        $this->managerUser->roles()->sync([$this->managerRole->id]);
    }

    public function test_report_service_returns_batches_prepared_stock_and_raw_consumption(): void
    {
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        $beans = $this->createInventoryItem('فول إنتاج', InventoryItemType::RawMaterial, 20, 10, $restaurant);
        $spices = $this->createInventoryItem('بهارات إنتاج', InventoryItemType::RawMaterial, 30, 5, $restaurant);
        $prepared = $this->createInventoryItem('معجون فلافل تقارير', InventoryItemType::PreparedItem, 0, 0);

        $recipe = ProductionRecipe::create([
            'prepared_item_id' => $prepared->id,
            'name' => 'دفعة تقارير فلافل',
            'output_quantity' => 5,
            'output_unit' => 'كجم',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $recipe->lines()->create([
            'inventory_item_id' => $beans->id,
            'quantity' => 3,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 1,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $recipe->lines()->create([
            'inventory_item_id' => $spices->id,
            'quantity' => 1,
            'unit' => 'كجم',
            'unit_conversion_rate' => 1,
            'sort_order' => 2,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        app(ProductionBatchService::class)->produce(
            recipe: $recipe,
            actualOutputQuantity: 6,
            actorId: $this->adminUser->id,
            location: $restaurant,
        );

        $service = app(ReportService::class);

        $batches = $service->getProductionBatchesReport();
        $preparedStock = $service->getPreparedItemStockByLocation();
        $consumption = $service->getProductionRawConsumption();

        $this->assertSame(1, $batches['summary']['batches_count']);
        $this->assertSame(6.0, $batches['summary']['total_output_quantity']);
        $this->assertSame(0.0, $batches['summary']['total_waste_quantity']);
        $this->assertSame(90.0, $batches['summary']['total_input_cost']);
        $this->assertCount(1, $preparedStock);
        $this->assertSame('معجون فلافل تقارير', $preparedStock->first()['item_name']);
        $this->assertSame(6.0, $preparedStock->first()['current_stock']);
        $this->assertSame(2, $consumption['summary']['lines_count']);
        $this->assertSame(4.0, $consumption['summary']['consumed_quantity']);
        $this->assertSame(90.0, $consumption['summary']['consumed_cost']);
    }

    public function test_admin_can_view_production_report_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/production-report')
            ->assertSuccessful()
            ->assertSee('تقرير الإنتاج والتحضير');
    }

    public function test_manager_without_permission_cannot_access_production_report_page(): void
    {
        $this->actingAs($this->managerUser)
            ->get('/admin/production-report')
            ->assertForbidden();

        $this->managerRole->givePermissionTo('reports.production.view');

        $this->actingAs($this->managerUser->fresh())
            ->get('/admin/production-report')
            ->assertSuccessful();
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
