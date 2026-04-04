<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeePenaltyResource\Pages;
use App\Models\Employee;
use App\Models\EmployeePenalty;
use App\Models\User;
use App\Support\HrFeature;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeePenaltyResource extends Resource
{
    protected static ?string $model = EmployeePenalty::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string | \UnitEnum | null $navigationGroup = 'HR';
    protected static ?string $navigationLabel = 'جزاءات الموظفين';
    protected static ?string $modelLabel = 'جزاء موظف';
    protected static ?string $pluralModelLabel = 'جزاءات الموظفين';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return HrFeature::hasPenaltyTables() && static::canViewAny();
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
            Forms\Components\DatePicker::make('penalty_date')
                ->label('تاريخ الجزاء')
                ->default(now()->toDateString())
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->label('قيمة الجزاء')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->prefix('ج.م')
                ->required(),
            Forms\Components\TextInput::make('reason')
                ->label('سبب الجزاء')
                ->required()
                ->maxLength(255),
            Forms\Components\Toggle::make('is_active')
                ->label('نشط')
                ->default(true),
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
                Tables\Columns\TextColumn::make('penalty_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('السبب')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('amount')
                    ->label('القيمة')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة'),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('الموظف')
                    ->options(static::employeeOptions()),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('penalty_date', 'desc');
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
            'index' => Pages\ListEmployeePenalties::route('/'),
            'create' => Pages\CreateEmployeePenalty::route('/create'),
            'edit' => Pages\EditEmployeePenalty::route('/{record}/edit'),
        ];
    }
}
