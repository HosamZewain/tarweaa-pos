<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPageExcelExport;
use App\Filament\Pages\Concerns\HasPagePermission;
use App\Models\Employee;
use App\Services\ReportService;
use App\Support\BusinessTime;
use App\Support\HrFeature;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class EmployeeAdvancesReport extends Page implements HasForms
{
    use HasPagePermission;
    use HasPageExcelExport;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wallet';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير سلف الموظفين';
    protected static ?string $title = 'تقرير سلف الموظفين';
    protected static ?int $navigationSort = 5;
    protected static string $permissionName = 'reports.employee_advances.view';

    protected string $view = 'filament.pages.employee-advances-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?int $user_id = null;
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
            Forms\Components\DatePicker::make('date_from')
                ->label('من تاريخ')
                ->required(),
            Forms\Components\DatePicker::make('date_to')
                ->label('إلى تاريخ')
                ->required(),
            Forms\Components\Select::make('user_id')
                ->label('الموظف')
                ->options(fn () => Employee::query()
                    ->manageable()
                    ->with('employeeProfile')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (Employee $employee): array => [
                        $employee->id => $employee->employeeProfile?->full_name ?: $employee->name,
                    ])
                    ->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('كل الموظفين'),
        ])->columns(3);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return HrFeature::hasAdvanceTables()
            && ($user?->hasPermission(static::$permissionName) ?? false);
    }

    public function generateReport(): void
    {
        $this->reportData = app(ReportService::class)->getEmployeeAdvancesReport(
            $this->date_from,
            $this->date_to,
            $this->user_id,
        );
    }
}
