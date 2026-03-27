<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Models\User;
use App\Services\ReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class DiscountsReport extends Page implements HasForms
{
    use HasPagePermission;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-ticket';
    protected static string | \UnitEnum | null $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'تقرير الخصومات';
    protected static ?string $title = 'سجل الخصومات';
    protected static ?int $navigationSort = 3;
    protected static string $permissionName = 'reports.discounts.view';

    protected string $view = 'filament.pages.discounts-report';

    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $search = null;
    public ?string $scope_filter = 'all';
    public ?int $applied_by = null;
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
            Forms\Components\Select::make('scope_filter')
                ->label('نوع الخصم')
                ->options([
                    'all' => 'الكل',
                    'order' => 'خصومات الطلبات',
                    'item' => 'خصومات الأصناف',
                ]),
            Forms\Components\Select::make('applied_by')
                ->label('تم بواسطة')
                ->options(
                    User::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()
                )
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('search')
                ->label('بحث')
                ->placeholder('رقم الطلب أو اسم العميل أو رقم الهاتف'),
        ])->columns(3);
    }

    public function generateReport(): void
    {
        $this->reportData = app(ReportService::class)->getDiscountAudit(
            dateFrom: $this->date_from,
            dateTo: $this->date_to,
            scope: $this->scope_filter,
            appliedBy: $this->applied_by,
            search: $this->search,
        );
    }
}
