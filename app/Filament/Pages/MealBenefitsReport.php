<?php

namespace App\Filament\Pages;

use App\Enums\MealBenefitLedgerEntryType;
use App\Filament\Pages\Concerns\HasPagePermission;
use App\Filament\Pages\Concerns\HasPageExcelExport;
use App\Models\User;
use App\Services\MealBenefitReportService;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class MealBenefitsReport extends Page implements HasForms
{
    use HasPagePermission;
    use HasPageExcelExport;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'كشف بدلات الوجبات';
    protected static ?string $title = 'كشف بدلات الوجبات والتحميل';
    protected static ?int $navigationSort = 7;
    protected static string $permissionName = 'reports.meal_benefits.view';

    protected string $view = 'filament.pages.meal-benefits-report';

    public ?string $reference_month = null;
    public ?string $entry_type = null;
    public ?int $user_id = null;
    public ?array $reportData = null;

    public function mount(): void
    {
        $this->reference_month = BusinessTime::today()->startOfMonth()->toDateString();
        $this->generateReport();
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\DatePicker::make('reference_month')
                ->label('الشهر المرجعي')
                ->required()
                ->displayFormat('Y-m-d'),
            Forms\Components\Select::make('entry_type')
                ->label('نوع الحركة')
                ->options([
                    '' => 'كل الحركات',
                    ...collect(MealBenefitLedgerEntryType::cases())->mapWithKeys(fn (MealBenefitLedgerEntryType $type) => [
                        $type->value => $type->label(),
                    ])->all(),
                ])
                ->native(false),
            Forms\Components\Select::make('user_id')
                ->label('المستخدم')
                ->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('كل المستخدمين'),
        ])->columns(3);
    }

    public function generateReport(): void
    {
        $reference = Carbon::parse($this->reference_month ?: BusinessTime::today()->toDateString());
        $this->reportData = app(MealBenefitReportService::class)->buildMonthlyReport(
            reference: $reference,
            userId: $this->user_id,
            entryType: $this->entry_type ?: null,
        );
    }
}
