<?php

namespace App\Filament\Resources\PurchaseResource\RelationManagers;

use App\Models\InventoryItem;
use App\Models\PurchaseItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'بنود الشراء والاستلام';

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('inventory_item_id')
                ->label('المادة')
                ->options(fn () => InventoryItem::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    $item = InventoryItem::query()->find($state);
                    $set('unit', $item?->unit);
                    $set('unit_price', $item?->unit_cost);
                }),
            Forms\Components\TextInput::make('unit')
                ->label('الوحدة')
                ->required()
                ->disabled()
                ->dehydrated(),
            Forms\Components\TextInput::make('unit_price')
                ->label('سعر الوحدة')
                ->numeric()
                ->required()
                ->prefix('ج.م')
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('total', round(((float) $get('quantity_ordered')) * (float) $state, 2))),
            Forms\Components\TextInput::make('quantity_ordered')
                ->label('الكمية المطلوبة')
                ->numeric()
                ->required()
                ->minValue(0.001)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('total', round(((float) $state) * (float) $get('unit_price'), 2))),
            Forms\Components\TextInput::make('quantity_received')
                ->label('الكمية المستلمة')
                ->numeric()
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('total')
                ->label('الإجمالي')
                ->numeric()
                ->disabled()
                ->dehydrated(),
            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
                ->columnSpanFull(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('inventoryItem.name')
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')->label('المادة')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('unit')->label('الوحدة'),
                Tables\Columns\TextColumn::make('unit_price')->label('سعر الوحدة')->money('EGP'),
                Tables\Columns\TextColumn::make('quantity_ordered')->label('المطلوب')->numeric(3),
                Tables\Columns\TextColumn::make('quantity_received')->label('المستلم')->numeric(3),
                Tables\Columns\TextColumn::make('pending_quantity')
                    ->label('المتبقي')
                    ->state(fn (PurchaseItem $record) => $record->pendingQuantity())
                    ->numeric(3),
                Tables\Columns\TextColumn::make('total')->label('الإجمالي')->money('EGP'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('إضافة بند')
                    ->visible(fn () => !in_array($this->getOwnerRecord()->status, ['received', 'cancelled'], true))
                    ->mutateDataUsing(function (array $data): array {
                        $data['quantity_received'] = 0;
                        $data['total'] = round(((float) $data['quantity_ordered']) * ((float) $data['unit_price']), 2);

                        return $data;
                    })
                    ->after(fn () => $this->getOwnerRecord()->refresh()->recalculate()),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->label('تعديل')
                    ->visible(fn (PurchaseItem $record) => !in_array($this->getOwnerRecord()->status, ['received', 'cancelled'], true) && (float) $record->quantity_received === 0.0)
                    ->mutateDataUsing(function (array $data): array {
                        $data['total'] = round(((float) $data['quantity_ordered']) * ((float) $data['unit_price']), 2);

                        return $data;
                    })
                    ->after(fn () => $this->getOwnerRecord()->refresh()->recalculate()),
                Actions\Action::make('receive')
                    ->label('استلام')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->visible(fn (PurchaseItem $record) => !in_array($this->getOwnerRecord()->status, ['cancelled', 'received'], true) && $record->pendingQuantity() > 0)
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('الكمية المستلمة')
                            ->numeric()
                            ->required()
                            ->minValue(0.001)
                            ->default(fn (PurchaseItem $record) => $record->pendingQuantity()),
                    ])
                    ->action(function (PurchaseItem $record, array $data): void {
                        $record->receive((float) $data['quantity']);
                        $this->getOwnerRecord()->refresh();
                    }),
                Actions\DeleteAction::make()
                    ->visible(fn (PurchaseItem $record) => !in_array($this->getOwnerRecord()->status, ['received', 'cancelled'], true) && (float) $record->quantity_received === 0.0)
                    ->after(fn () => $this->getOwnerRecord()->refresh()->recalculate()),
            ]);
    }
}
