<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Filament\Pages\Concerns\HasPageExcelExport;
use App\Enums\InventoryItemType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\ReportService;
use App\Support\BusinessTime;
use App\Support\ProductionFeature;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class ProductionReport extends Page implements HasForms
{
    use HasPagePermission;
    use HasPageExcelExport;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير الإنتاج';
    protected static ?string $title = 'تقرير الإنتاج والتحضير';
    protected static ?int $navigationSort = 7;
    protected static string $permissionName = 'reports.production.view';

    protected string $view = 'filament.pages.production-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?int $location_id = null;
    public ?int $prepared_item_id = null;
    public ?array $reportData = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ProductionFeature::isAvailable() && ($user?->hasPermission(static::$permissionName) ?? false);
    }

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
            Forms\Components\Select::make('prepared_item_id')
                ->label('المنتج المُحضّر')
                ->options(InventoryItem::query()
                    ->where('is_active', true)
                    ->where('item_type', InventoryItemType::PreparedItem->value)
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->placeholder('كل المنتجات المُحضّرة'),
        ])->columns(4);
    }

    public function generateReport(): void
    {
        $reportService = app(ReportService::class);

        $this->reportData = [
            'batches' => $reportService->getProductionBatchesReport(
                $this->date_from,
                $this->date_to,
                $this->location_id,
                $this->prepared_item_id,
            ),
            'prepared_stock' => $reportService->getPreparedItemStockByLocation(
                $this->location_id,
                $this->prepared_item_id,
            ),
            'consumption' => $reportService->getProductionRawConsumption(
                $this->date_from,
                $this->date_to,
                $this->location_id,
                $this->prepared_item_id,
            ),
        ];
    }
}
