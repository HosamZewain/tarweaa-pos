<?php

namespace App\Filament\Resources;

use App\Enums\InventoryTransactionType;
use App\Filament\Resources\InventoryTransactionResource\Pages;
use App\Models\InventoryTransaction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static string | \UnitEnum | null $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'حركات المخزون';
    protected static ?string $modelLabel = 'حركة مخزون';
    protected static ?string $pluralModelLabel = 'حركات المخزون';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // Immutable ledger — created via service layer only
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('المادة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('inventoryItem.category')
                    ->label('التصنيف')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color(fn (InventoryTransactionType $state) => match($state) {
                        InventoryTransactionType::Purchase,
                        InventoryTransactionType::Return,
                        InventoryTransactionType::TransferIn  => 'success',
                        InventoryTransactionType::SaleDeduction,
                        InventoryTransactionType::Waste,
                        InventoryTransactionType::TransferOut => 'danger',
                        InventoryTransactionType::Adjustment  => 'warning',
                    })
                    ->formatStateUsing(fn (InventoryTransactionType $state) => $state->label()),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('الكمية')
                    ->sortable()
                    ->color(fn ($state) => (float) $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => ((float)$state > 0 ? '+' : '') . number_format((float) $state, 3)),
                Tables\Columns\TextColumn::make('quantity_before')
                    ->label('قبل')
                    ->numeric(3)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('quantity_after')
                    ->label('بعد')
                    ->numeric(3)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('تكلفة الوحدة')
                    ->money('EGP')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('إجمالي التكلفة')
                    ->money('EGP')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('performer.name')
                    ->label('بواسطة')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(collect(InventoryTransactionType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                Tables\Filters\SelectFilter::make('inventory_item_id')
                    ->label('المادة')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->label('نطاق التاريخ')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\Filter::make('increments')
                    ->label('وارد فقط')
                    ->query(fn ($q) => $q->where('quantity', '>', 0)),
                Tables\Filters\Filter::make('decrements')
                    ->label('صادر فقط')
                    ->query(fn ($q) => $q->where('quantity', '<', 0)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryTransactions::route('/'),
        ];
    }
}
