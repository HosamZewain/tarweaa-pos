<?php

namespace App\Filament\Resources;

use App\Enums\InventoryItemType;
use App\Enums\InventoryTransactionType;
use App\Filament\Resources\InventoryItemResource\Pages;
use App\Filament\Resources\InventoryItemResource\RelationManagers\LocationStocksRelationManager;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\AdminActivityLogService;
use App\Services\RecipeService;
use App\Services\InventoryService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';
    protected static string | \UnitEnum | null $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'المخزون';
    protected static ?string $modelLabel = 'مادة مخزنية';
    protected static ?string $pluralModelLabel = 'المخزون';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = InventoryItem::active()->lowStock()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات المادة')->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                Forms\Components\TextInput::make('sku')->label('SKU')->maxLength(50)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('category')->label('التصنيف')->maxLength(100),
                Forms\Components\Select::make('item_type')
                    ->label('نوع المادة')
                    ->options(collect(InventoryItemType::cases())->mapWithKeys(fn (InventoryItemType $type) => [$type->value => $type->label()]))
                    ->default(InventoryItemType::RawMaterial->value)
                    ->required(),
                Forms\Components\TextInput::make('unit')->label('وحدة الأساس')->required()->maxLength(20)->placeholder('كجم, لتر, قطعة'),
                Forms\Components\TextInput::make('unit_cost')->label('متوسط تكلفة الوحدة')->numeric()->prefix('ج.م')
                    ->helperText('تُستخدم هذه التكلفة في حساب تكلفة الوصفات وخصم المخزون.'),
                Forms\Components\TextInput::make('current_stock')->label('المخزون الحالي')->numeric()->disabled()->dehydrated(false),
                Forms\Components\TextInput::make('minimum_stock')->label('الحد الأدنى')->numeric(),
                Forms\Components\TextInput::make('maximum_stock')->label('الحد الأقصى')->numeric(),
                Forms\Components\Select::make('default_supplier_id')->label('المورد الافتراضي')->relationship('defaultSupplier', 'name')->searchable()->preload()->nullable(),
                Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable(),
                Tables\Columns\TextColumn::make('category')->label('التصنيف')->sortable(),
                Tables\Columns\TextColumn::make('item_type')
                    ->label('نوع المادة')
                    ->badge()
                    ->formatStateUsing(fn (InventoryItemType|string|null $state) => $state instanceof InventoryItemType ? $state->label() : InventoryItemType::from((string) $state)->label()),
                Tables\Columns\TextColumn::make('current_stock')->label('المخزون')->sortable()
                    ->color(fn (InventoryItem $record) => $record->isLowStock() ? 'danger' : ($record->isOutOfStock() ? 'danger' : 'success')),
                Tables\Columns\TextColumn::make('unit')->label('وحدة الأساس'),
                Tables\Columns\TextColumn::make('minimum_stock')->label('الحد الأدنى'),
                Tables\Columns\TextColumn::make('unit_cost')->label('متوسط التكلفة')->money('EGP'),
                Tables\Columns\TextColumn::make('location_stocks_count')->label('مواقع مهيأة')->counts('locationStocks')->toggleable(),
                Tables\Columns\TextColumn::make('defaultSupplier.name')->label('المورد')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\Filter::make('low_stock')->label('مخزون منخفض')
                    ->query(fn ($query) => $query->lowStock()),
                Tables\Filters\SelectFilter::make('default_supplier_id')->label('المورد')
                    ->relationship('defaultSupplier', 'name'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('adjustLocationStock')
                    ->label('جرد موقع')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->hasPermission('inventory_items.adjust_stock'))
                    ->form([
                        Forms\Components\Select::make('location_id')
                            ->label('الموقع')
                            ->options(fn () => InventoryLocation::query()->active()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('new_quantity')->label('الكمية الجديدة')->numeric()->required(),
                        Forms\Components\Textarea::make('notes')->label('سبب التعديل')->required(),
                    ])
                    ->action(function (InventoryItem $record, array $data) {
                        abort_unless(auth()->user()?->hasPermission('inventory_items.adjust_stock'), 403);
                        try {
                            $location = InventoryLocation::query()->findOrFail($data['location_id']);
                            $oldQuantity = (float) $record->current_stock;
                            app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record, $data): void {
                                $location = InventoryLocation::query()->findOrFail($data['location_id']);
                                app(InventoryService::class)->adjustLocationTo(
                                    item: $record,
                                    location: $location,
                                    newQuantity: (float) $data['new_quantity'],
                                    actorId: auth()->id(),
                                    notes: $data['notes'],
                                );
                            });
                            $record->refresh();
                            app(AdminActivityLogService::class)->logAction(
                                action: 'stock_adjusted',
                                subject: $record,
                                description: 'تم تعديل رصيد موقع مخزني من شاشة المخزون.',
                                oldValues: ['current_stock' => $oldQuantity],
                                newValues: [
                                    'current_stock' => $record->current_stock,
                                    'location_id' => $location->id,
                                    'location_name' => $location->name,
                                    'reason' => $data['notes'],
                                ],
                            );
                            Notification::make()->title('تم تعديل رصيد الموقع بنجاح')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('addStock')
                    ->label('إضافة مخزون إلى موقع')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->hasPermission('inventory_items.add_stock'))
                    ->form([
                        Forms\Components\Select::make('location_id')
                            ->label('الموقع')
                            ->options(fn () => InventoryLocation::query()->active()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('quantity')->label('الكمية')->numeric()->required()->minValue(0.001),
                        Forms\Components\TextInput::make('unit_cost')->label('تكلفة الوحدة')->numeric()->prefix('ج.م'),
                        Forms\Components\Textarea::make('notes')->label('ملاحظات'),
                    ])
                    ->action(function (InventoryItem $record, array $data) {
                        abort_unless(auth()->user()?->hasPermission('inventory_items.add_stock'), 403);
                        try {
                            $location = InventoryLocation::query()->findOrFail($data['location_id']);
                            $oldQuantity = (float) $record->current_stock;
                            app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record, $data): void {
                                $location = InventoryLocation::query()->findOrFail($data['location_id']);
                                app(InventoryService::class)->addStock(
                                    $record,
                                    (float) $data['quantity'],
                                    auth()->id(),
                                    InventoryTransactionType::Purchase,
                                    !empty($data['unit_cost']) ? (float) $data['unit_cost'] : null,
                                    notes: $data['notes'] ?? null,
                                    location: $location,
                                );
                            });
                            $record->refresh();
                            app(AdminActivityLogService::class)->logAction(
                                action: 'stock_added',
                                subject: $record,
                                description: 'تمت إضافة رصيد مخزون من لوحة الإدارة.',
                                oldValues: ['current_stock' => $oldQuantity],
                                newValues: [
                                    'current_stock' => $record->current_stock,
                                    'location_id' => $location->id,
                                    'location_name' => $location->name,
                                    'quantity_added' => (float) $data['quantity'],
                                    'unit_cost' => !empty($data['unit_cost']) ? (float) $data['unit_cost'] : null,
                                    'notes' => $data['notes'] ?? null,
                                ],
                            );
                            Notification::make()->title('تمت إضافة المخزون بنجاح')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('globalCorrection')
                    ->label('تصحيح عام')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->hasPermission('inventory_items.adjust_stock'))
                    ->form([
                        Forms\Components\TextInput::make('new_quantity')->label('الرصيد العام الجديد')->numeric()->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('سبب التصحيح')
                            ->required()
                            ->helperText('يستخدم فقط لتصحيح الرصيد الإجمالي في الحالات الاستثنائية.'),
                    ])
                    ->action(function (InventoryItem $record, array $data) {
                        abort_unless(auth()->user()?->hasPermission('inventory_items.adjust_stock'), 403);
                        try {
                            $oldQuantity = (float) $record->current_stock;
                            app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record, $data): void {
                                app(InventoryService::class)->adjustTo(
                                    item: $record,
                                    newQuantity: (float) $data['new_quantity'],
                                    actorId: auth()->id(),
                                    notes: $data['notes'],
                                );
                            });
                            $record->refresh();
                            app(AdminActivityLogService::class)->logAction(
                                action: 'stock_global_corrected',
                                subject: $record,
                                description: 'تم تنفيذ تصحيح عام على الرصيد الإجمالي للمخزون.',
                                oldValues: ['current_stock' => $oldQuantity],
                                newValues: [
                                    'current_stock' => $record->current_stock,
                                    'reason' => $data['notes'],
                                ],
                            );
                            Notification::make()->title('تم تنفيذ التصحيح العام')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            LocationStocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'edit'   => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
