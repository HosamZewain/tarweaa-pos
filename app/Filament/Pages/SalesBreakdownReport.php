<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\ReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;

class SalesBreakdownReport extends Page implements HasForms
{
    use HasPagePermission;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تفصيل المبيعات';
    protected static ?string $title = 'تفصيل المبيعات (كاشير / وردية)';
    protected static ?int $navigationSort = 2;
    protected static string $permissionName = 'reports.sales_breakdown.view';

    protected string $view = 'filament.pages.sales-breakdown-report';

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
        $service = app(ReportService::class);

        $this->reportData = [
            'byCashier' => $service->getSalesByCashier($this->date_from, $this->date_to),
            'byShift'   => $service->getSalesByShift($this->date_from, $this->date_to),
        ];
    }
}
