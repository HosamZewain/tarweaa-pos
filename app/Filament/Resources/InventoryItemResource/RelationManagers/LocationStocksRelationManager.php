<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Services\AdminActivityLogService;
use App\Services\InventoryService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LocationStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'locationStocks';

    protected static ?string $title = 'أرصدة المواقع';

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('inventory_location_id')
                ->label('الموقع')
                ->options(function (?InventoryLocationStock $record = null): array {
                    $existingLocationIds = $this->getOwnerRecord()
                        ->locationStocks()
                        ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                        ->pluck('inventory_location_id');

                    return InventoryLocation::query()
                        ->active()
                        ->whereNotIn('id', $existingLocationIds)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all();
                })
                ->required()
                ->searchable()
                ->preload()
                ->disabledOn('edit'),
            Forms\Components\TextInput::make('current_stock')
                ->label('الرصيد الحالي')
                ->numeric()
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('minimum_stock')
                ->label('الحد الأدنى')
                ->numeric()
                ->default(fn () => (float) $this->getOwnerRecord()->minimum_stock),
            Forms\Components\TextInput::make('maximum_stock')
                ->label('الحد الأقصى')
                ->numeric()
                ->default(fn () => (float) $this->getOwnerRecord()->maximum_stock),
            Forms\Components\TextInput::make('unit_cost')
                ->label('تكلفة الوحدة')
                ->numeric()
                ->prefix('ج.م')
                ->disabled()
                ->dehydrated(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('inventoryLocation.name')
            ->columns([
                Tables\Columns\TextColumn::make('inventoryLocation.name')
                    ->label('الموقع')
                    ->sortable(),
                Tables\Columns\TextColumn::make('inventoryLocation.type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'warehouse' => 'مخزن',
                        'restaurant' => 'مطعم',
                        default => 'آخر',
                    }),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('الرصيد')
                    ->numeric(3),
                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('الحد الأدنى')
                    ->numeric(3),
                Tables\Columns\TextColumn::make('maximum_stock')
                    ->label('الحد الأقصى')
                    ->numeric(3),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('تكلفة الوحدة')
                    ->money('EGP'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('تهيئة رصيد موقع')
                    ->visible(fn (): bool => $this->canUpdateInventoryItem())
                    ->mutateDataUsing(function (array $data): array {
                        $data['current_stock'] = 0;
                        $data['unit_cost'] = $this->getOwnerRecord()->unit_cost;

                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->label('تعديل الحدود')
                    ->visible(fn (): bool => $this->canUpdateInventoryItem()),
                Actions\Action::make('addStock')
                    ->label('إضافة')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (): bool => $this->canAddStock())
                    ->form([
                        Forms\Components\TextInput::make('quantity')->label('الكمية')->numeric()->required()->minValue(0.001),
                        Forms\Components\TextInput::make('unit_cost')->label('تكلفة الوحدة')->numeric()->prefix('ج.م'),
                        Forms\Components\Textarea::make('notes')->label('ملاحظات'),
                    ])
                    ->action(function (InventoryLocationStock $record, array $data): void {
                        abort_unless($this->canAddStock(), 403);

                        app(InventoryService::class)->addStock(
                            item: $record->inventoryItem,
                            quantity: (float) $data['quantity'],
                            actorId: auth()->id(),
                            type: InventoryTransactionType::Purchase,
                            unitCost: !empty($data['unit_cost']) ? (float) $data['unit_cost'] : null,
                            notes: $data['notes'] ?? null,
                            location: $record->inventoryLocation,
                        );
                    }),
                Actions\Action::make('adjustStock')
                    ->label('تسجيل جرد')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->visible(fn (): bool => $this->canAdjustStock())
                    ->form([
                        Forms\Components\TextInput::make('new_quantity')
                            ->label('الكمية المعدودة')
                            ->numeric()
                            ->required()
                            ->default(fn (InventoryLocationStock $record) => (float) $record->current_stock),
                        Forms\Components\Textarea::make('notes')->label('ملاحظات الجرد')->required(),
                    ])
                    ->action(function (InventoryLocationStock $record, array $data): void {
                        abort_unless($this->canAdjustStock(), 403);

                        try {
                            $oldLocationQuantity = (float) $record->current_stock;
                            $oldGlobalQuantity = (float) $record->inventoryItem->current_stock;

                            app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record, $data): void {
                                app(InventoryService::class)->adjustLocationTo(
                                    item: $record->inventoryItem,
                                    location: $record->inventoryLocation,
                                    newQuantity: (float) $data['new_quantity'],
                                    actorId: auth()->id(),
                                    notes: $data['notes'],
                                );
                            });

                            $record->refresh();
                            $record->inventoryItem->refresh();

                            app(AdminActivityLogService::class)->logAction(
                                action: 'stock_count_recorded',
                                subject: $record->inventoryItem,
                                description: 'تم تسجيل جرد موقع مخزني من بطاقة أرصدة المواقع.',
                                oldValues: [
                                    'global_stock' => $oldGlobalQuantity,
                                    'location_stock' => $oldLocationQuantity,
                                ],
                                newValues: [
                                    'global_stock' => $record->inventoryItem->current_stock,
                                    'location_stock' => $record->current_stock,
                                    'counted_quantity' => (float) $data['new_quantity'],
                                    'variance' => round((float) $data['new_quantity'] - $oldLocationQuantity, 3),
                                    'location_id' => $record->inventory_location_id,
                                    'location_name' => $record->inventoryLocation?->name,
                                    'notes' => $data['notes'],
                                ],
                            );

                            Notification::make()->title('تم تسجيل الجرد بنجاح')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    private function canUpdateInventoryItem(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    private function canAddStock(): bool
    {
        return $this->canUpdateInventoryItem() && (auth()->user()?->hasPermission('inventory_items.add_stock') ?? false);
    }

    private function canAdjustStock(): bool
    {
        return $this->canUpdateInventoryItem() && (auth()->user()?->hasPermission('inventory_items.adjust_stock') ?? false);
    }
}
