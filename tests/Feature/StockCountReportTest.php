<?php

namespace Tests\Feature;

use App\Filament\Pages\StockCountsReport;
use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class StockCountReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_count_report_returns_location_count_rows_and_supports_filters(): void
    {
        $this->artisan('db:seed');

        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
        $warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();
        $performer = User::factory()->create(['name' => 'Count Performer', 'is_active' => true]);
        $otherUser = User::factory()->create(['name' => 'Other Performer', 'is_active' => true]);

        $item = InventoryItem::create([
            'name' => 'جبنة',
            'unit' => 'كجم',
            'unit_cost' => 30,
            'current_stock' => 20,
            'minimum_stock' => 2,
            'maximum_stock' => 50,
            'is_active' => true,
            'created_by' => $performer->id,
            'updated_by' => $performer->id,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $restaurant->id,
            'current_stock' => 8,
            'minimum_stock' => 1,
            'maximum_stock' => 20,
            'unit_cost' => 30,
            'created_by' => $performer->id,
            'updated_by' => $performer->id,
        ]);

        InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $warehouse->id,
            'current_stock' => 12,
            'minimum_stock' => 1,
            'maximum_stock' => 30,
            'unit_cost' => 30,
            'created_by' => $performer->id,
            'updated_by' => $performer->id,
        ]);

        $this->travelTo(now()->startOfDay()->addHours(9));
        app(InventoryService::class)->adjustLocationTo(
            item: $item,
            location: $restaurant,
            newQuantity: 6,
            actorId: $performer->id,
            notes: 'جرد صباحي',
        );

        $this->travelTo(now()->startOfDay()->addDay()->addHours(11));
        app(InventoryService::class)->adjustLocationTo(
            item: $item,
            location: $warehouse,
            newQuantity: 13,
            actorId: $otherUser->id,
            notes: 'جرد مخزن',
        );

        DB::table('inventory_transactions')->insert([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => null,
            'type' => 'adjustment',
            'quantity' => 5,
            'quantity_before' => 19,
            'quantity_after' => 24,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'legacy global adjustment',
            'performed_by' => $performer->id,
            'created_by' => $performer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(ReportService::class)->getStockCountVariances(
            dateFrom: now()->subDays(3)->toDateString(),
            dateTo: now()->toDateString(),
        );

        $this->assertCount(2, $report['entries']);
        $this->assertSame(1, $report['summary']['increase_count']);
        $this->assertSame(1, $report['summary']['decrease_count']);
        $this->assertSame('المخزن الرئيسي', $report['entries']->first()['location_name']);
        $this->assertSame('جبنة', $report['entries']->first()['item_name']);
        $this->assertSame('Other Performer', $report['entries']->first()['performed_by_name']);
        $this->assertSame(13.0, $report['entries']->first()['counted_quantity']);
        $this->assertSame(1.0, $report['entries']->first()['variance']);

        $filtered = app(ReportService::class)->getStockCountVariances(
            dateFrom: now()->subDays(3)->toDateString(),
            dateTo: now()->toDateString(),
            day: now()->subDay()->toDateString(),
            locationId: $restaurant->id,
            itemId: $item->id,
            performedBy: $performer->id,
        );

        $this->assertCount(1, $filtered['entries']);
        $this->assertSame('المطعم', $filtered['entries']->first()['location_name']);
        $this->assertSame('Count Performer', $filtered['entries']->first()['performed_by_name']);
        $this->assertSame(-2.0, $filtered['entries']->first()['variance']);
        $this->assertSame(6.0, $filtered['entries']->first()['counted_quantity']);
    }

    public function test_generic_inventory_adjust_api_no_longer_mutates_stock(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $item = InventoryItem::create([
            'name' => 'سكر',
            'unit' => 'كجم',
            'unit_cost' => 15,
            'current_stock' => 10,
            'minimum_stock' => 1,
            'maximum_stock' => 40,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/inventory/{$item->id}/adjust", [
            'type' => 'adjustment',
            'quantity' => 5,
            'notes' => 'unsafe direct correction',
        ])->assertStatus(403);

        $this->assertSame('10.000', $item->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_inventory_list_no_longer_shows_general_correction_action(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        InventoryItem::create([
            'name' => 'عدس',
            'unit' => 'كجم',
            'unit_cost' => 10,
            'current_stock' => 5,
            'minimum_stock' => 1,
            'maximum_stock' => 20,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ListInventoryItems::class)
            ->assertDontSee('تصحيح عام');
    }

    public function test_stock_counts_report_can_export_excel(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(StockCountsReport::class)
            ->callAction('exportExcel')
            ->assertFileDownloaded(contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
