<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPageExcelExport;
use App\Filament\Pages\Concerns\HasPagePermission;
use App\Services\ReportService;
use Filament\Pages\Page;

class HrOverview extends Page
{
    use HasPagePermission;
    use HasPageExcelExport;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-identification';
    protected static string | \UnitEnum | null $navigationGroup = 'HR';
    protected static ?string $navigationLabel = 'نظرة عامة HR';
    protected static ?string $title = 'نظرة عامة HR';
    protected static ?int $navigationSort = 0;
    protected static string $permissionName = 'hr.overview.view';

    protected string $view = 'filament.pages.hr-overview';

    public ?array $reportData = null;

    public function mount(): void
    {
        $this->reportData = app(ReportService::class)->getHrOverview();
    }
}
