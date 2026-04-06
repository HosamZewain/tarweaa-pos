<?php

namespace Tests\Feature;

use App\Filament\Pages\InventoryLocationsReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryLocationsReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_locations_report_can_export_excel(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(InventoryLocationsReport::class)
            ->callAction('exportExcel')
            ->assertFileDownloaded(contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
