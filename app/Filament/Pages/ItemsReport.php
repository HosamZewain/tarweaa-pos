<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPageExcelExport;
use App\Filament\Pages\Concerns\HasPagePermission;
use App\Models\MenuItem;
use App\Services\ReportService;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class ItemsReport extends Page implements HasForms
{
    use HasPagePermission;
    use HasPageExcelExport;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير الأصناف';
    protected static ?string $title = 'تقرير الأصناف';
    protected static ?int $navigationSort = 2;
    protected static string $permissionName = 'reports.items.view';

    protected string $view = 'filament.pages.items-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?int $menu_item_id = null;
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
            Forms\Components\Select::make('menu_item_id')
                ->label('صنف محدد')
                ->options(fn () => MenuItem::query()->ordered()->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->nullable()
                ->placeholder('كل الأصناف'),
        ])->columns(3);
    }

    public function generateReport(): void
    {
        $this->reportData = app(ReportService::class)->getItemsReport(
            $this->date_from,
            $this->date_to,
            $this->menu_item_id,
        );
    }
}
