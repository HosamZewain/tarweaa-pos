<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\ReportService;
use Filament\Pages\Page;

class InventoryReport extends Page
{
    use HasPagePermission;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير المخزون';
    protected static ?string $title = 'تقرير المخزون';
    protected static ?int $navigationSort = 2;
    protected static string $permissionName = 'reports.inventory.view';

    protected string $view = 'filament.pages.inventory-report';

    public ?array $valuation = null;

    public function mount(): void
    {
        $this->valuation = app(ReportService::class)->getInventoryValuation();
    }
}
