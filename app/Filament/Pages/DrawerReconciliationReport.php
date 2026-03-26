<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\ReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;

class DrawerReconciliationReport extends Page implements HasForms
{
    use HasPagePermission;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تسوية الأدراج';
    protected static ?string $title = 'تقرير تسوية الأدراج';
    protected static ?int $navigationSort = 3;
    protected static string $permissionName = 'reports.drawer_reconciliation.view';

    protected string $view = 'filament.pages.drawer-reconciliation-report';

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

        $sessions = $service->getDrawersReconciliation($this->date_from, $this->date_to, 100);
        $variance = $service->getCashVarianceReport($this->date_from, $this->date_to);

        $this->reportData = [
            'sessions' => $sessions,
            'variance' => $variance,
            'totals'   => [
                'total_sessions'     => $sessions->total(),
                'total_opening'      => round($sessions->getCollection()->sum('opening_balance'), 2),
                'total_closing'      => round($sessions->getCollection()->sum('closing_balance'), 2),
                'total_difference'   => round($sessions->getCollection()->sum('cash_difference'), 2),
                'sessions_with_diff' => $sessions->getCollection()->filter(fn ($s) => abs((float) $s->cash_difference) > 0)->count(),
            ],
        ];
    }
}
