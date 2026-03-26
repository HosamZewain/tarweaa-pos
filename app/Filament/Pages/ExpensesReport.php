<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\ReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;

class ExpensesReport extends Page implements HasForms
{
    use HasPagePermission;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير المصروفات';
    protected static ?string $title = 'تقرير المصروفات';
    protected static ?int $navigationSort = 4;
    protected static string $permissionName = 'reports.expenses.view';

    protected string $view = 'filament.pages.expenses-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?array $reportData = null;

    public function mount(): void
    {
        $this->date_from = today()->startOfMonth()->toDateString();
        $this->date_to = today()->toDateString();
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
        $this->reportData = app(ReportService::class)->getExpensesReport($this->date_from, $this->date_to);
    }
}
