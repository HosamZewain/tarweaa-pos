<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\ReportService;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class PlatformTransfersReport extends Page implements HasForms
{
    use HasPagePermission;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-library';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير تحويلات المنصات';
    protected static ?string $title = 'تقرير تحويلات المنصات';
    protected static ?int $navigationSort = 5;
    protected static string $permissionName = 'reports.platform_transfers.view';

    protected string $view = 'filament.pages.platform-transfers-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public array $methods = [];
    public ?array $reportData = null;

    public function mount(): void
    {
        $this->date_from = BusinessTime::today()->startOfMonth()->toDateString();
        $this->date_to = BusinessTime::today()->toDateString();
        $this->methods = array_keys($this->getMethodOptions());

        $this->generateReport();
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\DatePicker::make('date_from')->label('من تاريخ')->required(),
            Forms\Components\DatePicker::make('date_to')->label('إلى تاريخ')->required(),
            Forms\Components\Select::make('methods')
                ->label('طرق الدفع')
                ->multiple()
                ->searchable()
                ->options($this->getMethodOptions())
                ->required(),
        ])->columns(3);
    }

    public function generateReport(): void
    {
        $this->reportData = app(ReportService::class)->getPlatformTransfersReport(
            dateFrom: $this->date_from,
            dateTo: $this->date_to,
            methods: $this->methods,
        );
    }

    protected function getMethodOptions(): array
    {
        return [
            PaymentMethod::TalabatPay->value => PaymentMethod::TalabatPay->label(),
            PaymentMethod::JahezPay->value => PaymentMethod::JahezPay->label(),
            PaymentMethod::Online->value => PaymentMethod::Online->label(),
            PaymentMethod::InstaPay->value => PaymentMethod::InstaPay->label(),
        ];
    }
}
