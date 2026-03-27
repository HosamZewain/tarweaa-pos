<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\ReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class CardTerminalReport extends Page implements HasForms
{
    use HasPagePermission;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير أجهزة الدفع';
    protected static ?string $title = 'تقرير أجهزة الدفع';
    protected static ?int $navigationSort = 4;
    protected static string $permissionName = 'reports.card_terminals.view';

    protected string $view = 'filament.pages.card-terminal-report';

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
        $this->reportData = app(ReportService::class)->getCardPaymentsByTerminal(
            dateFrom: $this->date_from,
            dateTo: $this->date_to,
        );
    }
}
