<?php

namespace App\Filament\Resources;

use App\Enums\ProductionBatchStatus;
use App\Filament\Resources\ProductionBatchResource\Pages;
use App\Models\InventoryLocation;
use App\Models\ProductionBatch;
use App\Models\ProductionRecipe;
use App\Support\BusinessTime;
use App\Support\ProductionFeature;
use App\Services\ManagerVerificationService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionBatchResource extends Resource
{
    protected static ?string $model = ProductionBatch::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';
    protected static string | \UnitEnum | null $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'دفعات الإنتاج';
    protected static ?string $modelLabel = 'دفعة إنتاج';
    protected static ?string $pluralModelLabel = 'دفعات الإنتاج';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return ProductionFeature::isAvailable() && static::canViewAny();
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات الدفعة')->schema([
                Forms\Components\Select::make('production_recipe_id')
                    ->label('وصفة الإنتاج')
                    ->options(fn () => static::recipeOptions())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $recipe = ProductionRecipe::query()
                            ->with(['preparedItem', 'lines.inventoryItem'])
                            ->find($state);

                        if (!$recipe) {
                            $set('actual_output_quantity', null);
                            $set('output_unit_preview', null);
                            $set('input_quantities_payload', []);

                            return;
                        }

                        $set('actual_output_quantity', (float) $recipe->output_quantity);
                        $set('output_unit_preview', $recipe->output_unit);
                        $set('input_quantities_payload', static::recipeLinePayload($recipe));
                    }),
                Forms\Components\Select::make('inventory_location_id')
                    ->label('موقع الإنتاج')
                    ->relationship('location', 'name', fn ($query) => $query->active()->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => app(\App\Services\InventoryLocationService::class)->defaultProductionLocation()?->id),
                Forms\Components\TextInput::make('actual_output_quantity')
                    ->label('كمية الناتج الفعلية')
                    ->numeric()
                    ->required()
                    ->minValue(0.001),
                Forms\Components\TextInput::make('waste_quantity')
                    ->label('كمية الفاقد')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Forms\Components\TextInput::make('output_unit_preview')
                    ->label('وحدة الناتج')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('waste_notes')
                    ->label('سبب / ملاحظات الفاقد')
                    ->columnSpanFull(),
                Forms\Components\Select::make('approver_id')
                    ->label('اعتماد بواسطة')
                    ->options(fn () => static::approverOptions())
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('approver_pin')
                    ->label('PIN المعتمد')
                    ->password()
                    ->revealable()
                    ->required(),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('استهلاك المكونات الفعلي')->schema([
                Forms\Components\Repeater::make('input_quantities_payload')
                    ->label('المكونات')
                    ->schema([
                        Forms\Components\Hidden::make('production_recipe_line_id'),
                        Forms\Components\TextInput::make('item_name')
                            ->label('المادة')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('planned_quantity_display')
                            ->label('الكمية القياسية')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('actual_quantity')
                            ->label('الكمية الفعلية')
                            ->numeric()
                            ->required()
                            ->minValue(0.001),
                        Forms\Components\TextInput::make('unit')
                            ->label('الوحدة')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(4)
                    ->default(fn ($get) => static::recipeLinePayload(ProductionRecipe::query()->with(['lines.inventoryItem'])->find($get('production_recipe_id'))))
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull(),
            ])->visible(fn ($get) => filled($get('production_recipe_id'))),
        ]);
    }

    public static function table(Table $table): Table
    {
        $businessTimezone = BusinessTime::timezone();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('رقم الدفعة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('preparedItem.name')
                    ->label('المنتج المُحضّر')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productionRecipe.name')
                    ->label('الوصفة')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('الموقع')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (ProductionBatchStatus|string|null $state) => $state instanceof ProductionBatchStatus ? $state->label() : ($state ? ProductionBatchStatus::from($state)->label() : '—')),
                Tables\Columns\TextColumn::make('actual_output_quantity')
                    ->label('الناتج الفعلي')
                    ->formatStateUsing(fn ($state, ProductionBatch $record) => number_format((float) $state, 3) . ' ' . $record->output_unit),
                Tables\Columns\TextColumn::make('waste_quantity')
                    ->label('الفاقد')
                    ->formatStateUsing(fn ($state, ProductionBatch $record) => (float) $state > 0 ? number_format((float) $state, 3) . ' ' . $record->output_unit : '—'),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('تكلفة الوحدة')
                    ->money('EGP'),
                Tables\Columns\TextColumn::make('approver.name')
                    ->label('اعتمد بواسطة')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_input_cost')
                    ->label('إجمالي تكلفة المدخلات')
                    ->money('EGP'),
                Tables\Columns\TextColumn::make('produced_at')
                    ->label('وقت الإنتاج')
                    ->dateTime()
                    ->timezone($businessTimezone)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('prepared_item_id')
                    ->label('المنتج المُحضّر')
                    ->relationship('preparedItem', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('inventory_location_id')
                    ->label('الموقع')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(collect(ProductionBatchStatus::cases())->mapWithKeys(fn (ProductionBatchStatus $status) => [$status->value => $status->label()])),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->defaultSort('produced_at', 'desc');
    }

    public static function infolist(Schema $infolist): Schema
    {
        $businessTimezone = BusinessTime::timezone();

        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('بيانات الدفعة')->schema([
                Infolists\Components\TextEntry::make('batch_number')->label('رقم الدفعة'),
                Infolists\Components\TextEntry::make('preparedItem.name')->label('المنتج المُحضّر'),
                Infolists\Components\TextEntry::make('productionRecipe.name')->label('الوصفة')->placeholder('—'),
                Infolists\Components\TextEntry::make('location.name')->label('الموقع'),
                Infolists\Components\TextEntry::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (ProductionBatchStatus|string|null $state) => $state instanceof ProductionBatchStatus ? $state->label() : ($state ? ProductionBatchStatus::from($state)->label() : '—')),
                Infolists\Components\TextEntry::make('planned_output_quantity')
                    ->label('الناتج القياسي')
                    ->state(fn (ProductionBatch $record) => number_format((float) $record->planned_output_quantity, 3) . ' ' . $record->output_unit),
                Infolists\Components\TextEntry::make('actual_output_quantity')
                    ->label('الناتج الفعلي')
                    ->state(fn (ProductionBatch $record) => number_format((float) $record->actual_output_quantity, 3) . ' ' . $record->output_unit),
                Infolists\Components\TextEntry::make('waste_quantity')
                    ->label('الفاقد')
                    ->state(fn (ProductionBatch $record) => (float) $record->waste_quantity > 0 ? number_format((float) $record->waste_quantity, 3) . ' ' . $record->output_unit : '—'),
                Infolists\Components\TextEntry::make('total_input_cost')->label('إجمالي تكلفة المدخلات')->money('EGP'),
                Infolists\Components\TextEntry::make('unit_cost')->label('تكلفة الوحدة الناتجة')->money('EGP'),
                Infolists\Components\TextEntry::make('yield_variance_quantity')
                    ->label('فرق العائد')
                    ->state(fn (ProductionBatch $record) => number_format((float) $record->yield_variance_quantity, 3) . ' ' . $record->output_unit),
                Infolists\Components\TextEntry::make('yield_variance_percentage')
                    ->label('نسبة فرق العائد')
                    ->state(fn (ProductionBatch $record) => number_format((float) $record->yield_variance_percentage, 2) . '%'),
                Infolists\Components\TextEntry::make('producer.name')->label('تم الإنتاج بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('approver.name')->label('اعتمد بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('approved_at')->label('وقت الاعتماد')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('produced_at')->label('وقت الإنتاج')->dateTime()->timezone($businessTimezone),
                Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('waste_notes')->label('ملاحظات الفاقد')->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('voidedBy.name')->label('أُلغي بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('voided_at')->label('وقت الإلغاء')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('void_reason')->label('سبب الإلغاء')->placeholder('—')->columnSpanFull(),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('تفاصيل الاستهلاك')->schema([
                Infolists\Components\RepeatableEntry::make('lines')->label('')->schema([
                    Infolists\Components\TextEntry::make('inventoryItem.name')->label('المادة'),
                    Infolists\Components\TextEntry::make('planned_quantity')
                        ->label('الكمية القياسية')
                        ->state(fn ($record) => number_format((float) $record->planned_quantity, 3) . ' ' . $record->unit),
                    Infolists\Components\TextEntry::make('actual_quantity')
                        ->label('الكمية الفعلية')
                        ->state(fn ($record) => number_format((float) $record->actual_quantity, 3) . ' ' . $record->unit),
                    Infolists\Components\TextEntry::make('base_quantity')
                        ->label('الكمية الأساسية')
                        ->state(fn ($record) => number_format((float) $record->base_quantity, 6)),
                    Infolists\Components\TextEntry::make('unit_cost')->label('تكلفة الوحدة')->money('EGP'),
                    Infolists\Components\TextEntry::make('total_cost')->label('إجمالي التكلفة')->money('EGP'),
                ])->columns(3),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductionBatches::route('/'),
            'create' => Pages\CreateProductionBatch::route('/create'),
            'view' => Pages\ViewProductionBatch::route('/{record}'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function recipeOptions(): array
    {
        return ProductionRecipe::query()
            ->with('preparedItem')
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(function (ProductionRecipe $recipe): array {
                $preparedName = $recipe->preparedItem?->name ?? '—';
                $recipeName = $recipe->name ?: 'بدون اسم';

                return [$recipe->id => "{$preparedName} - {$recipeName}"];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function approverOptions(): array
    {
        return collect(app(ManagerVerificationService::class)->listApprovers())
            ->mapWithKeys(fn (array $user) => [$user['id'] => trim($user['name'] . ' (' . ($user['username'] ?: '—') . ')')])
            ->all();
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    public static function recipeLinePayload(?ProductionRecipe $recipe): array
    {
        if (!$recipe) {
            return [];
        }

        $recipe->loadMissing('lines.inventoryItem');

        return $recipe->lines
            ->map(fn ($line) => [
                'production_recipe_line_id' => $line->id,
                'item_name' => $line->inventoryItem?->name ?? '—',
                'planned_quantity_display' => number_format((float) $line->quantity, 3),
                'actual_quantity' => (float) $line->quantity,
                'unit' => $line->unit,
            ])
            ->all();
    }
}
