<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Filament\Pages\Concerns\HasPageExcelExport;
use App\Services\ReportService;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;

class SalesReport extends Page implements HasForms
{
    use HasPagePermission;
    use HasPageExcelExport;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير المبيعات';
    protected static ?string $title = 'تقرير المبيعات';
    protected static ?int $navigationSort = 1;
    protected static string $permissionName = 'reports.sales.view';

    protected string $view = 'filament.pages.sales-report';

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
        $reportService = app(ReportService::class);

        $dailySales = $reportService->getDailySales($this->date_from, $this->date_to);
        $salesByItem = $reportService->getSalesByItem($this->date_from, $this->date_to);
        $salesByCategory = $reportService->getSalesByCategory($this->date_from, $this->date_to);
        $salesByPayment = $reportService->getSalesByPaymentMethod($this->date_from, $this->date_to);

        $this->reportData = [
            'dailySales'      => $dailySales,
            'salesByItem'     => $salesByItem,
            'salesByCategory' => $salesByCategory,
            'salesByPayment'  => $salesByPayment,
        ];
    }
}
