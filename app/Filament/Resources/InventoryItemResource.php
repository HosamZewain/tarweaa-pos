<?php

namespace App\Filament\Resources;

use App\Enums\InventoryTransactionType;
use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
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
                Tables\Columns\TextColumn::make('current_stock')->label('المخزون')->sortable()
                    ->color(fn (InventoryItem $record) => $record->isLowStock() ? 'danger' : ($record->isOutOfStock() ? 'danger' : 'success')),
                Tables\Columns\TextColumn::make('unit')->label('وحدة الأساس'),
                Tables\Columns\TextColumn::make('minimum_stock')->label('الحد الأدنى'),
                Tables\Columns\TextColumn::make('unit_cost')->label('متوسط التكلفة')->money('EGP'),
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
                \Filament\Actions\Action::make('adjustStock')
                    ->label('تعديل المخزون')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->hasPermission('inventory_items.adjust_stock'))
                    ->form([
                        Forms\Components\TextInput::make('new_quantity')->label('الكمية الجديدة')->numeric()->required(),
                        Forms\Components\Textarea::make('notes')->label('سبب التعديل')->required(),
                    ])
                    ->action(function (InventoryItem $record, array $data) {
                        abort_unless(auth()->user()?->hasPermission('inventory_items.adjust_stock'), 403);
                        try {
                            app(InventoryService::class)->adjustTo(
                                $record,
                                (float) $data['new_quantity'],
                                auth()->id(),
                                $data['notes'],
                            );
                            Notification::make()->title('تم تعديل المخزون بنجاح')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('addStock')
                    ->label('إضافة مخزون')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->hasPermission('inventory_items.add_stock'))
                    ->form([
                        Forms\Components\TextInput::make('quantity')->label('الكمية')->numeric()->required()->minValue(0.001),
                        Forms\Components\TextInput::make('unit_cost')->label('تكلفة الوحدة')->numeric()->prefix('ج.م'),
                        Forms\Components\Textarea::make('notes')->label('ملاحظات'),
                    ])
                    ->action(function (InventoryItem $record, array $data) {
                        abort_unless(auth()->user()?->hasPermission('inventory_items.add_stock'), 403);
                        try {
                            app(InventoryService::class)->addStock(
                                $record,
                                (float) $data['quantity'],
                                auth()->id(),
                                InventoryTransactionType::Purchase,
                                !empty($data['unit_cost']) ? (float) $data['unit_cost'] : null,
                                notes: $data['notes'] ?? null,
                            );
                            Notification::make()->title('تمت إضافة المخزون بنجاح')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('name');
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
