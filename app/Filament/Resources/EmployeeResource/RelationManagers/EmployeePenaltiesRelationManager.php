<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeePenaltiesRelationManager extends RelationManager
{
    protected static string $relationship = 'employeePenalties';

    protected static ?string $title = 'الجزاءات';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
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
                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('إضافة جزاء'),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->label('تعديل'),
            ])
            ->defaultSort('penalty_date', 'desc');
    }
}
