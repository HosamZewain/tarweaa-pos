<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryLocationResource\Pages;
use App\Models\InventoryLocation;
use App\Support\ProductionFeature;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryLocationResource extends Resource
{
    protected static ?string $model = InventoryLocation::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';
    protected static string | \UnitEnum | null $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'مواقع المخزون';
    protected static ?string $modelLabel = 'موقع مخزني';
    protected static ?string $pluralModelLabel = 'مواقع المخزون';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات الموقع')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('اسم الموقع')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('الكود')
                    ->required()
                    ->alphaDash()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('type')
                    ->label('النوع')
                    ->required()
                    ->options([
                        'warehouse' => 'مخزن',
                        'restaurant' => 'مطعم',
                        'other' => 'آخر',
                    ]),
                Forms\Components\Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
                Forms\Components\Toggle::make('is_default_purchase_destination')
                    ->label('افتراضي لاستلام المشتريات'),
                Forms\Components\Toggle::make('is_default_recipe_deduction_location')
                    ->label('افتراضي لخصم الوصفات'),
                Forms\Components\Toggle::make('is_default_production_location')
                    ->label('افتراضي للإنتاج والتحضير')
                    ->visible(ProductionFeature::hasProductionLocationFlag()),
                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('الكود')->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'warehouse' => 'مخزن',
                        'restaurant' => 'مطعم',
                        default => 'آخر',
                    }),
                Tables\Columns\IconColumn::make('is_default_purchase_destination')
                    ->label('افتراضي للمشتريات')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_default_recipe_deduction_location')
                    ->label('افتراضي للوصفات')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_default_production_location')
                    ->label('افتراضي للإنتاج')
                    ->boolean()
                    ->visible(ProductionFeature::hasProductionLocationFlag()),
                Tables\Columns\TextColumn::make('stocks_count')
                    ->label('أرصدة المواد')
                    ->counts('stocks'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'warehouse' => 'مخزن',
                        'restaurant' => 'مطعم',
                        'other' => 'آخر',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryLocations::route('/'),
            'create' => Pages\CreateInventoryLocation::route('/create'),
            'edit' => Pages\EditInventoryLocation::route('/{record}/edit'),
        ];
    }
}
