<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\ReportService;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;

class InventoryMovementsReport extends Page implements HasForms
{
    use HasPagePermission;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'حركة المخزون';
    protected static ?string $title = 'تقرير حركة المخزون';
    protected static ?int $navigationSort = 5;
    protected static string $permissionName = 'reports.inventory_movements.view';

    protected string $view = 'filament.pages.inventory-movements-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
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
        ])->columns(2);
    }

    public function generateReport(): void
    {
        $movements = app(ReportService::class)->getInventoryMovements($this->date_from, $this->date_to);

        $this->reportData = [
            'movements' => $movements,
            'total_items' => $movements->count(),
        ];
    }
}
