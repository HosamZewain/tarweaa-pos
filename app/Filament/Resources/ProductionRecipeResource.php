<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionRecipeResource\Pages;
use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use App\Support\ProductionFeature;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionRecipeResource extends Resource
{
    protected static ?string $model = ProductionRecipe::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-beaker';
    protected static string | \UnitEnum | null $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'وصفات الإنتاج';
    protected static ?string $modelLabel = 'وصفة إنتاج';
    protected static ?string $pluralModelLabel = 'وصفات الإنتاج';
    protected static ?int $navigationSort = 2;

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
            \Filament\Schemas\Components\Section::make('بيانات الوصفة')->schema([
                Forms\Components\Select::make('prepared_item_id')
                    ->label('المنتج المُحضّر')
                    ->relationship('preparedItem', 'name', fn ($query) => $query->preparedItems()->where('is_active', true)->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $item = InventoryItem::query()->find($state);
                        $set('output_unit', $item?->unit);
                    }),
                Forms\Components\TextInput::make('name')
                    ->label('اسم الوصفة')
                    ->maxLength(255)
                    ->placeholder('مثال: دفعة طحينة 5 كجم'),
                Forms\Components\TextInput::make('output_quantity')
                    ->label('كمية الناتج القياسية')
                    ->numeric()
                    ->required()
                    ->minValue(0.001),
                Forms\Components\TextInput::make('output_unit')
                    ->label('وحدة الناتج')
                    ->required()
                    ->maxLength(20),
                Forms\Components\Toggle::make('is_active')
                    ->label('نشطة')
                    ->default(true),
                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('مكونات الإنتاج')->schema([
                Forms\Components\Repeater::make('lines')
                    ->relationship()
                    ->label('المكونات')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->schema([
                        Forms\Components\Select::make('inventory_item_id')
                            ->label('المادة')
                            ->options(fn () => InventoryItem::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $item = InventoryItem::query()->find($state);
                                $set('unit', $item?->unit);
                            }),
                        Forms\Components\TextInput::make('quantity')
                            ->label('الكمية القياسية')
                            ->numeric()
                            ->required()
                            ->minValue(0.001),
                        Forms\Components\TextInput::make('unit')
                            ->label('الوحدة')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('unit_conversion_rate')
                            ->label('معامل التحويل لوحدة الأساس')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0.000001),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('الترتيب')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('preparedItem.name')
                    ->label('المنتج المُحضّر')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الوصفة')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('output_quantity')
                    ->label('الناتج القياسي')
                    ->formatStateUsing(fn ($state, ProductionRecipe $record) => number_format((float) $state, 3) . ' ' . $record->output_unit)
                    ->sortable(),
                Tables\Columns\TextColumn::make('lines_count')
                    ->label('عدد المكونات')
                    ->counts('lines'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة'),
                Tables\Filters\SelectFilter::make('prepared_item_id')
                    ->label('المنتج المُحضّر')
                    ->relationship('preparedItem', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductionRecipes::route('/'),
            'create' => Pages\CreateProductionRecipe::route('/create'),
            'edit' => Pages\EditProductionRecipe::route('/{record}/edit'),
        ];
    }
}
