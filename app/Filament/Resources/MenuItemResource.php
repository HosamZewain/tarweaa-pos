<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cake';
    protected static string | \UnitEnum | null $navigationGroup = 'القائمة';
    protected static ?string $navigationLabel = 'الأصناف';
    protected static ?string $modelLabel = 'صنف';
    protected static ?string $pluralModelLabel = 'الأصناف';
    protected static ?int $navigationSort = 2;
    protected static ?Collection $inventoryLookup = null;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات الصنف')->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                Forms\Components\Select::make('category_id')->label('الفئة')->relationship('category', 'name')->required()->searchable()->preload(),
                Forms\Components\Textarea::make('description')->label('الوصف')->maxLength(500),
                Forms\Components\TextInput::make('sku')->label('SKU')->maxLength(50),
                Forms\Components\Select::make('type')->label('النوع')->options(['simple' => 'بسيط', 'variable' => 'متعدد'])->default('simple')->required(),
                Forms\Components\TextInput::make('base_price')->label('السعر الأساسي')->numeric()->prefix('ج.م')->required()->live(onBlur: true),
                Forms\Components\TextInput::make('cost_price')->label('سعر التكلفة اليدوي')->numeric()->prefix('ج.م')
                    ->helperText('عند تعريف وصفة للصنف سيتم استخدام تكلفة الوصفة تلقائياً.'),
                Forms\Components\TextInput::make('preparation_time')->label('وقت التحضير (دقائق)')->numeric(),
                Forms\Components\FileUpload::make('image')->label('الصورة')->image()->directory('menu-items')->nullable(),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('الحالة')->schema([
                Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
                Forms\Components\Toggle::make('is_available')->label('متاح')->default(true),
                Forms\Components\Toggle::make('track_inventory')->label('تتبع المخزون')->default(false),
            ])->columns(3),
            \Filament\Schemas\Components\Section::make('الخيارات (Variants)')
                ->schema([
                    Forms\Components\Repeater::make('variants')
                        ->relationship('variants')
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('الاسم (مثلاً: صغير)')->required(),
                            Forms\Components\TextInput::make('price')->label('السعر')->numeric()->required()->prefix('ج.م'),
                            Forms\Components\TextInput::make('sku')->label('SKU'),
                            Forms\Components\Toggle::make('is_available')->label('متاح')->default(true),
                        ])
                        ->columns(4)
                        ->visible(fn (Get $get) => $get('type') === 'variable'),
                ]),
            \Filament\Schemas\Components\Section::make('الوصفة والتكلفة')
                ->description('اربط الصنف بمكونات المخزون مع الكميات المستخدمة ومعامل التحويل لوحدة المخزون الأساسية.')
                ->schema([
                    Forms\Components\Placeholder::make('recipe_cost_preview')
                        ->label('إجمالي تكلفة الوصفة')
                        ->content(fn (Get $get) => static::formatMoney(static::recipeCostFromState($get))),
                    Forms\Components\Placeholder::make('food_cost_percentage_preview')
                        ->label('نسبة تكلفة الغذاء')
                        ->content(fn (Get $get) => number_format(static::foodCostPercentageFromState($get), 2) . '%'),
                    Forms\Components\Placeholder::make('profit_margin_preview')
                        ->label('هامش الربح')
                        ->content(fn (Get $get) => static::formatMoney(static::profitMarginFromState($get)) . ' (' . number_format(static::profitMarginPercentageFromState($get), 2) . '%)'),
                    Forms\Components\Repeater::make('recipeLines')
                        ->relationship('recipeLines')
                        ->label('مكونات الوصفة')
                        ->addActionLabel('إضافة مكوّن')
                        ->itemLabel(fn (array $state): ?string => static::recipeLineLabel($state))
                        ->orderColumn('sort_order')
                        ->reorderableWithButtons()
                        ->collapsed()
                        ->defaultItems(0)
                        ->schema([
                            Forms\Components\Select::make('inventory_item_id')
                                ->label('المكوّن')
                                ->options(fn () => static::inventoryOptions())
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                    $inventoryItem = static::inventoryItemFor($state);

                                    if (!$inventoryItem) {
                                        return;
                                    }

                                    if (blank($get('unit'))) {
                                        $set('unit', $inventoryItem->unit);
                                    }

                                    if (blank($get('unit_conversion_rate')) || (float) $get('unit_conversion_rate') <= 0) {
                                        $set('unit_conversion_rate', 1);
                                    }
                                }),
                            Forms\Components\TextInput::make('quantity')
                                ->label('الكمية المستخدمة')
                                ->numeric()
                                ->required()
                                ->minValue(0.001)
                                ->default(1)
                                ->live(onBlur: true),
                            Forms\Components\TextInput::make('unit')
                                ->label('وحدة الوصفة')
                                ->required()
                                ->placeholder('مثل: جم أو مل')
                                ->helperText(fn (Get $get) => 'وحدة المخزون الأساسية: ' . (static::inventoryBaseUnit($get('inventory_item_id')) ?? '—'))
                                ->live(onBlur: true),
                            Forms\Components\TextInput::make('unit_conversion_rate')
                                ->label('معامل التحويل')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->minValue(0.000001)
                                ->step('0.000001')
                                ->helperText('كم وحدة أساسية من المخزون تمثل وحدة وصفة واحدة. مثال: 1 جم = 0.001 كجم')
                                ->live(onBlur: true),
                            Forms\Components\Placeholder::make('inventory_unit_cost_preview')
                                ->label('متوسط تكلفة وحدة الأساس')
                                ->content(fn (Get $get) => static::inventoryUnitCostPreview($get)),
                            Forms\Components\Placeholder::make('base_quantity_preview')
                                ->label('الكمية بوحدة المخزون')
                                ->content(fn (Get $get) => static::baseQuantityPreview($get)),
                            Forms\Components\Placeholder::make('line_cost_preview')
                                ->label('تكلفة السطر')
                                ->content(fn (Get $get) => static::lineCostPreview($get)),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('الصورة')->circular(),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('الفئة')->sortable(),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge(),
                Tables\Columns\TextColumn::make('recipe_cost')
                    ->label('تكلفة الوصفة')
                    ->getStateUsing(fn (MenuItem $record) => $record->recipeCost())
                    ->money('EGP')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('food_cost')
                    ->label('تكلفة الغذاء')
                    ->getStateUsing(fn (MenuItem $record) => number_format($record->foodCostPercentage(), 2) . '%'),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('السعر')
                    ->getStateUsing(fn (MenuItem $record) => 
                        $record->type === 'variable' 
                            ? ($record->variants->isEmpty() ? '0.00' : $record->variants->min('price') . ' - ' . $record->variants->max('price'))
                            : $record->base_price
                    )
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_available')->label('متاح')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('الفئة')->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')->label('نشط'),
                Tables\Filters\TernaryFilter::make('is_available')->label('متاح'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('toggleAvailability')
                    ->label(fn (MenuItem $record) => $record->is_available ? 'غير متاح' : 'متاح')
                    ->icon('heroicon-o-eye')
                    ->color(fn (MenuItem $record) => $record->is_available ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (MenuItem $record) => $record->toggleAvailability()),
            ])
            ->bulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit'   => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }

    protected static function inventoryLookup(): Collection
    {
        return static::$inventoryLookup ??= InventoryItem::query()
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'unit_cost', 'is_active']);
    }

    protected static function inventoryOptions(): array
    {
        return static::inventoryLookup()
            ->mapWithKeys(function (InventoryItem $item) {
                $inactive = $item->is_active ? '' : ' - غير نشط';

                return [(string) $item->id => "{$item->name} ({$item->unit}){$inactive}"];
            })
            ->all();
    }

    protected static function inventoryItemFor(mixed $inventoryItemId): ?InventoryItem
    {
        if (blank($inventoryItemId)) {
            return null;
        }

        return static::inventoryLookup()->firstWhere('id', (int) $inventoryItemId);
    }

    protected static function inventoryBaseUnit(mixed $inventoryItemId): ?string
    {
        return static::inventoryItemFor($inventoryItemId)?->unit;
    }

    protected static function recipeLineLabel(array $state): ?string
    {
        $inventoryItem = static::inventoryItemFor($state['inventory_item_id'] ?? null);

        if (!$inventoryItem) {
            return 'مكوّن جديد';
        }

        $quantity = (float) ($state['quantity'] ?? 0);
        $unit = trim((string) ($state['unit'] ?? ''));

        if ($quantity <= 0) {
            return $inventoryItem->name;
        }

        $formattedQuantity = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
        $suffix = $unit !== '' ? " {$unit}" : '';

        return "{$inventoryItem->name} - {$formattedQuantity}{$suffix}";
    }

    protected static function inventoryUnitCostPreview(Get $get): string
    {
        $inventoryItem = static::inventoryItemFor($get('inventory_item_id'));

        if (!$inventoryItem) {
            return '—';
        }

        return static::formatMoney((float) ($inventoryItem->unit_cost ?? 0));
    }

    protected static function baseQuantityPreview(Get $get): string
    {
        $inventoryItem = static::inventoryItemFor($get('inventory_item_id'));

        if (!$inventoryItem) {
            return '—';
        }

        $quantity = (float) ($get('quantity') ?? 0);
        $conversionRate = (float) ($get('unit_conversion_rate') ?? 1);
        $baseQuantity = app(\App\Services\RecipeService::class)->calculateBaseQuantity($quantity, $conversionRate);

        return number_format($baseQuantity, 3) . ' ' . $inventoryItem->unit;
    }

    protected static function lineCostPreview(Get $get): string
    {
        $inventoryItem = static::inventoryItemFor($get('inventory_item_id'));

        if (!$inventoryItem) {
            return '—';
        }

        $cost = app(\App\Services\RecipeService::class)->calculateLineCost(
            quantity: (float) ($get('quantity') ?? 0),
            conversionRate: (float) ($get('unit_conversion_rate') ?? 1),
            inventoryItem: $inventoryItem,
        );

        return static::formatMoney($cost);
    }

    protected static function recipeCostFromState(Get $get): float
    {
        return app(\App\Services\RecipeService::class)
            ->calculateRecipeCostFromState($get('recipeLines') ?? []);
    }

    protected static function foodCostPercentageFromState(Get $get): float
    {
        $service = app(\App\Services\RecipeService::class);

        return $service->calculateFoodCostPercentage(
            static::recipeCostFromState($get),
            (float) ($get('base_price') ?? 0),
        );
    }

    protected static function profitMarginFromState(Get $get): float
    {
        $service = app(\App\Services\RecipeService::class);

        return $service->calculateProfitMarginAmount(
            static::recipeCostFromState($get),
            (float) ($get('base_price') ?? 0),
        );
    }

    protected static function profitMarginPercentageFromState(Get $get): float
    {
        $service = app(\App\Services\RecipeService::class);

        return $service->calculateProfitMarginPercentage(
            static::recipeCostFromState($get),
            (float) ($get('base_price') ?? 0),
        );
    }

    protected static function formatMoney(float $amount): string
    {
        return number_format($amount, 2) . ' ج.م';
    }
}
