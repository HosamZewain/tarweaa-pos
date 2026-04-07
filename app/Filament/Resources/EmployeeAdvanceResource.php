<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeAdvanceResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\User;
use App\Services\EmployeeAdvanceService;
use App\Support\HrFeature;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeAdvanceResource extends Resource
{
    protected static ?string $model = EmployeeAdvance::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wallet';
    protected static string | \UnitEnum | null $navigationGroup = 'HR';
    protected static ?string $navigationLabel = 'سلف الموظفين';
    protected static ?string $modelLabel = 'سلفة موظف';
    protected static ?string $pluralModelLabel = 'سلف الموظفين';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return HrFeature::hasAdvanceTables() && static::canViewAny();
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
                ->required()
                ->disabledOn('edit'),
            Forms\Components\TextInput::make('amount')
                ->label('قيمة السلفة')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->prefix('ج.م')
                ->required(),
            Forms\Components\DatePicker::make('advance_date')
                ->label('تاريخ السلفة')
                ->default(now()->toDateString())
                ->required(),
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options([
                    'active' => 'نشطة',
                    'cancelled' => 'ملغاة',
                ])
                ->disabled()
                ->visibleOn('edit'),
            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('cancellation_reason')
                ->label('سبب الإلغاء')
                ->rows(3)
                ->columnSpanFull()
                ->disabled()
                ->visible(fn (?EmployeeAdvance $record): bool => $record?->isCancelled() ?? false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee.employeeProfile', 'employee.roles', 'creator:id,name', 'canceller:id,name']))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('الموظف')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.employeeProfile.job_title')
                    ->label('المسمى الوظيفي')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('قيمة السلفة')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('advance_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'cancelled' ? 'ملغاة' : 'نشطة')
                    ->color(fn (string $state): string => $state === 'cancelled' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('تمت بواسطة')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('canceller.name')
                    ->label('ألغيت بواسطة')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'نشطة',
                        'cancelled' => 'ملغاة',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('الموظف')
                    ->options(static::employeeOptions()),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->visible(fn (EmployeeAdvance $record): bool => auth()->user()?->can('update', $record) ?? false),
                \Filament\Actions\Action::make('cancelAdvance')
                    ->label('إلغاء')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (EmployeeAdvance $record): bool => auth()->user()?->can('cancel', $record) ?? false)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('سبب الإلغاء')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (EmployeeAdvance $record, array $data): void {
                        abort_unless(auth()->user()?->can('cancel', $record), 403);
                        app(EmployeeAdvanceService::class)->cancel($record, $data['reason'] ?? null);
                    }),
            ])
            ->defaultSort('advance_date', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee.employeeProfile', 'employee.roles', 'creator:id,name', 'canceller:id,name']);
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
            'index' => Pages\ListEmployeeAdvances::route('/'),
            'create' => Pages\CreateEmployeeAdvance::route('/create'),
            'edit' => Pages\EditEmployeeAdvance::route('/{record}/edit'),
        ];
    }
}
