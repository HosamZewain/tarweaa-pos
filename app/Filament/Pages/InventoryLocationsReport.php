<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Filament\Pages\Concerns\HasPageExcelExport;
use App\Models\InventoryLocation;
use App\Services\ReportService;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class InventoryLocationsReport extends Page implements HasForms
{
    use HasPagePermission;
    use HasPageExcelExport;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير مخزون المواقع';
    protected static ?string $title = 'تقرير المخزون متعدد المواقع';
    protected static ?int $navigationSort = 6;
    protected static string $permissionName = 'reports.inventory_locations.view';

    protected string $view = 'filament.pages.inventory-locations-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?int $location_id = null;
    public ?array $reportData = null;

    public function mount(): void
    {
        $this->date_from = BusinessTime::today()->startOfMonth()->toDateString();
        $this->date_to = BusinessTime::today()->toDateString();
        $this->generateReport();
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\DatePicker::make('date_from')->label('من تاريخ')->required(),
            Forms\Components\DatePicker::make('date_to')->label('إلى تاريخ')->required(),
            Forms\Components\Select::make('location_id')
                ->label('الموقع')
                ->options(InventoryLocation::query()->active()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('كل المواقع'),
        ])->columns(3);
    }

    public function generateReport(): void
    {
        $reportService = app(ReportService::class);

        $this->reportData = [
            'valuation' => $reportService->getInventoryValuationByLocation($this->location_id),
            'stock_rows' => $reportService->getStockByLocation($this->location_id),
            'low_stock_rows' => $reportService->getLowStockByLocation($this->location_id),
            'purchases' => $reportService->getPurchasesByLocation($this->date_from, $this->date_to, $this->location_id),
            'received' => $reportService->getReceivedStockByLocation($this->date_from, $this->date_to, $this->location_id),
            'transfers' => $reportService->getInventoryTransfersReport($this->date_from, $this->date_to, $this->location_id),
            'reconciliation' => $reportService->getStockReconciliation(),
        ];
    }
}
