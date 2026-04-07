<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeAdvance;
use App\Services\EmployeeAdvanceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeAdvancesRelationManager extends RelationManager
{
    protected static string $relationship = 'employeeAdvances';

    protected static ?string $title = 'السلف';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
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
            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('advance_date')
            ->columns([
                Tables\Columns\TextColumn::make('advance_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('قيمة السلفة')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'cancelled' ? 'ملغاة' : 'نشطة')
                    ->color(fn (string $state): string => $state === 'cancelled' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(50)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('cancellation_reason')
                    ->label('سبب الإلغاء')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('إضافة سلفة'),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->label('تعديل')
                    ->visible(fn (EmployeeAdvance $record): bool => auth()->user()?->can('update', $record) ?? false),
                Actions\Action::make('cancelAdvance')
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
}
