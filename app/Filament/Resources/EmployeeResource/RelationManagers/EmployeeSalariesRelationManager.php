<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeSalary;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeSalariesRelationManager extends RelationManager
{
    protected static string $relationship = 'employeeSalaries';

    protected static ?string $title = 'الرواتب';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('effective_from')
            ->columns([
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
                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('إضافة راتب'),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->label('تعديل'),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
