<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPageExcelExport;
use App\Filament\Pages\Concerns\HasPagePermission;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\User;
use App\Services\ReportService;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class StockCountsReport extends Page implements HasForms
{
    use HasPagePermission;
    use HasPageExcelExport;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير الجرد';
    protected static ?string $title = 'تقرير الجرد وفروقات المخزون';
    protected static ?int $navigationSort = 7;
    protected static string $permissionName = 'reports.stock_counts.view';

    protected string $view = 'filament.pages.stock-counts-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $day = null;
    public ?int $location_id = null;
    public ?int $inventory_item_id = null;
    public ?int $performed_by = null;
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
            Forms\Components\DatePicker::make('date_from')->label('من تاريخ'),
            Forms\Components\DatePicker::make('date_to')->label('إلى تاريخ'),
            Forms\Components\DatePicker::make('day')->label('يوم محدد')->helperText('اختياري لتضييق النتائج على يوم واحد.'),
            Forms\Components\Select::make('location_id')
                ->label('الموقع')
                ->options(InventoryLocation::query()->active()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('كل المواقع'),
            Forms\Components\Select::make('inventory_item_id')
                ->label('المادة')
                ->options(InventoryItem::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('كل المواد'),
            Forms\Components\Select::make('performed_by')
                ->label('نفذ بواسطة')
                ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->placeholder('كل المستخدمين'),
        ])->columns(3);
    }

    public function generateReport(): void
    {
        $this->reportData = app(ReportService::class)->getStockCountVariances(
            dateFrom: $this->date_from,
            dateTo: $this->date_to,
            day: $this->day,
            locationId: $this->location_id,
            itemId: $this->inventory_item_id,
            performedBy: $this->performed_by,
        );
    }
}
