<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeSalaryResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\User;
use App\Support\HrFeature;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeSalaryResource extends Resource
{
    protected static ?string $model = EmployeeSalary::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | \UnitEnum | null $navigationGroup = 'HR';
    protected static ?string $navigationLabel = 'رواتب الموظفين';
    protected static ?string $modelLabel = 'راتب موظف';
    protected static ?string $pluralModelLabel = 'رواتب الموظفين';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return HrFeature::hasSalaryTables() && static::canViewAny();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('الموظف')
                ->options(static::employeeOptions())
                ->searchable()
                ->preload()
                ->native(false)
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->label('قيمة الراتب')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->prefix('ج.م')
                ->required(),
            Forms\Components\DatePicker::make('effective_from')
                ->label('ساري من')
                ->default(now()->toDateString())
                ->required(),
            Forms\Components\DatePicker::make('effective_to')
                ->label('ساري حتى')
                ->helperText('اختياري. اتركه فارغًا إذا كان الراتب الحالي مستمرًا.'),
            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee.employeeProfile', 'employee.roles']))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('الموظف')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.employeeProfile.job_title')
                    ->label('المسمى الوظيفي')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('الراتب')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('effective_from')
                    ->label('ساري من')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('effective_to')
                    ->label('ساري حتى')
                    ->date()
                    ->placeholder('مستمر'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->state(function (EmployeeSalary $record): string {
                        $today = today();

                        return match (true) {
                            $record->effective_from?->isFuture() => 'مجدول',
                            $record->effective_to && $record->effective_to->lt($today) => 'منتهي',
                            default => 'ساري',
                        };
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ساري' => 'success',
                        'مجدول' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('الموظف')
                    ->options(static::employeeOptions()),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('effective_from', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee.employeeProfile', 'employee.roles']);
    }

    protected static function employeeOptions(): array
    {
        return Employee::query()
            ->manageable()
            ->with(['roles', 'employeeProfile'])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (User $employee): array {
                $label = $employee->employeeProfile?->full_name ?: $employee->name;
                $jobTitle = $employee->employeeProfile?->job_title;

                if ($jobTitle) {
                    $label .= " - {$jobTitle}";
                }

                return [$employee->id => $label];
            })
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeSalaries::route('/'),
            'create' => Pages\CreateEmployeeSalary::route('/create'),
            'edit' => Pages\EditEmployeeSalary::route('/{record}/edit'),
        ];
    }
}
