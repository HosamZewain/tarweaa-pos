<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPrintPageAction;
use App\Filament\Pages\Concerns\HasPagePermission;
use App\Models\PayrollRun;
use App\Services\AdminExcelExportService;
use App\Services\PayrollService;
use App\Support\BusinessTime;
use App\Support\HrFeature;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Throwable;

class Payroll extends Page implements HasForms
{
    use HasPagePermission;
    use HasPrintPageAction;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | \UnitEnum | null $navigationGroup = 'HR';
    protected static ?string $navigationLabel = 'Payroll';
    protected static ?string $title = 'Payroll';
    protected static ?int $navigationSort = 3;
    protected static string $permissionName = 'hr.payroll.view';

    protected string $view = 'filament.pages.payroll';

    public ?string $payroll_month = null;
    public ?array $reportData = null;
    public ?int $currentRunId = null;

    public function mount(): void
    {
        $this->payroll_month = BusinessTime::today()->format('Y-m');
        $this->loadPayroll();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return HrFeature::hasPayrollTables()
            && HrFeature::hasSalaryTables()
            && HrFeature::hasPenaltyTables()
            && HrFeature::hasAdvanceTables()
            && ($user?->hasPermission(static::$permissionName) ?? false);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('payroll_month')
                ->label('شهر المسير')
                ->type('month')
                ->required(),
        ])->columns(1);
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->reportData) {
            $actions[] = $this->makePrintPageAction();

            $actions[] = Action::make('exportExcel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(AdminExcelExportService::class)->downloadFromData(
                    $this->getExcelExportFilename(),
                    $this->reportData ?? [],
                ));
        }

        $actions[] = Action::make('generatePayroll')
            ->label($this->hasDraftRun() ? 'إعادة توليد المسير' : 'توليد مسير الرواتب')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('primary')
            ->visible(fn (): bool => ($this->reportData['run']['status'] ?? null) !== 'approved'
                && (auth()->user()?->hasPermission('hr.payroll.generate') ?? false))
            ->requiresConfirmation()
            ->action(fn () => $this->generatePayroll());

        $actions[] = Action::make('approvePayroll')
            ->label('اعتماد المسير')
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->visible(fn (): bool => $this->hasDraftRun() && (auth()->user()?->hasPermission('hr.payroll.approve') ?? false))
            ->requiresConfirmation()
            ->action(fn () => $this->approvePayroll());

        return $actions;
    }

    public function loadPayroll(): void
    {
        $this->validate([
            'payroll_month' => ['required', 'date_format:Y-m'],
        ]);

        if (!$this->payroll_month) {
            return;
        }

        $service = app(PayrollService::class);
        $run = $service->getRunForMonth($this->payroll_month);

        if ($run) {
            $this->currentRunId = $run->id;
            $this->reportData = $service->payloadForRun($run);

            return;
        }

        $this->currentRunId = null;
        $this->reportData = $service->previewMonth($this->payroll_month);
    }

    public function generatePayroll(): void
    {
        abort_unless(auth()->user()?->hasPermission('hr.payroll.generate') ?? false, 403);

        try {
            $run = app(PayrollService::class)->generateMonth($this->payroll_month, auth()->user());
            $this->currentRunId = $run->id;
            $this->reportData = app(PayrollService::class)->payloadForRun($run);

            Notification::make()
                ->title('تم توليد مسير الرواتب')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function approvePayroll(): void
    {
        abort_unless(auth()->user()?->hasPermission('hr.payroll.approve') ?? false, 403);

        if (!$this->currentRunId) {
            return;
        }

        try {
            $run = app(PayrollService::class)->approve(
                PayrollRun::query()->findOrFail($this->currentRunId),
                auth()->user(),
            );

            $this->reportData = app(PayrollService::class)->payloadForRun($run);

            Notification::make()
                ->title('تم اعتماد مسير الرواتب')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function hasDraftRun(): bool
    {
        return ($this->reportData['run']['status'] ?? null) === 'draft';
    }

    private function getExcelExportFilename(): string
    {
        return Str::slug('payroll-' . ($this->payroll_month ?? now()->format('Y-m'))) . '.xlsx';
    }
}
