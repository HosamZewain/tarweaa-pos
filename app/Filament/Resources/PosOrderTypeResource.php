<?php

namespace App\Filament\Resources;

use App\Enums\ChannelPricingRuleType;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Filament\Resources\PosOrderTypeResource\Pages;
use App\Models\PosOrderType;
use App\Support\BusinessTime;
use App\Services\ChannelPricingService;
use App\Services\PosOrderTypeService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PosOrderTypeResource extends Resource
{
    protected static ?string $model = PosOrderType::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static string | \UnitEnum | null $navigationGroup = 'الإعدادات';
    protected static ?string $navigationLabel = 'أنواع الطلبات';
    protected static ?string $modelLabel = 'نوع طلب';
    protected static ?string $pluralModelLabel = 'أنواع الطلبات';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('الاسم الظاهر')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('type')
                ->label('النوع الداخلي')
                ->options(collect(OrderType::cases())->mapWithKeys(fn (OrderType $type) => [
                    $type->value => $type->label(),
                ])->all())
                ->required()
                ->native(false),
            Forms\Components\Select::make('source')
                ->label('المصدر')
                ->options(collect(OrderSource::cases())->mapWithKeys(fn (OrderSource $source) => [
                    $source->value => $source->label(),
                ])->all())
                ->default(OrderSource::Pos->value)
                ->required()
                ->native(false),
            Forms\Components\Select::make('pricing_rule_type')
                ->label('قاعدة التسعير الافتراضية')
                ->options(collect(ChannelPricingRuleType::cases())->mapWithKeys(fn (ChannelPricingRuleType $type) => [
                    $type->value => $type->label(),
                ])->all())
                ->default(ChannelPricingRuleType::BasePrice->value)
                ->required()
                ->native(false)
                ->live(),
            Forms\Components\TextInput::make('pricing_rule_value')
                ->label('قيمة القاعدة')
                ->numeric()
                ->default(0)
                ->step('0.01')
                ->helperText('مثال: 20 لزيادة 20%، أو -10 لخفض 10%، أو 5 لإضافة 5 جنيهات.')
                ->visible(fn (Get $get) => $get('pricing_rule_type') !== ChannelPricingRuleType::BasePrice->value),
            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->numeric()
                ->default(0)
                ->minValue(0),
            Forms\Components\Toggle::make('is_active')
                ->label('نشط')
                ->default(true),
            Forms\Components\Toggle::make('is_default')
                ->label('الافتراضي في الـPOS')
                ->helperText('يمكن أن يوجد نوع افتراضي واحد فقط، وسيُختار تلقائيًا عند فتح نقطة البيع.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $businessTimezone = BusinessTime::timezone();

        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع الداخلي')
                    ->badge(),
                Tables\Columns\TextColumn::make('source')
                    ->label('المصدر')
                    ->badge(),
                Tables\Columns\TextColumn::make('pricing_rule_summary')
                    ->label('قاعدة التسعير')
                    ->state(fn (PosOrderType $record) => app(ChannelPricingService::class)->ruleSummary($record))
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('افتراضي')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('محذوف')
                    ->since()
                    ->timezone($businessTimezone)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->label('أرشفة')
                    ->visible(fn (\App\Models\PosOrderType $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->after(fn () => app(PosOrderTypeService::class)->ensureDefaultExists()),
                \Filament\Actions\RestoreAction::make()
                    ->visible(fn (\App\Models\PosOrderType $record): bool => auth()->user()?->can('restore', $record) ?? false)
                    ->after(fn () => app(PosOrderTypeService::class)->ensureDefaultExists()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->label('أرشفة المحدد')
                        ->after(fn () => app(PosOrderTypeService::class)->ensureDefaultExists()),
                    \Filament\Actions\RestoreBulkAction::make()
                        ->after(fn () => app(PosOrderTypeService::class)->ensureDefaultExists()),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosOrderTypes::route('/'),
            'create' => Pages\CreatePosOrderType::route('/create'),
            'edit' => Pages\EditPosOrderType::route('/{record}/edit'),
        ];
    }
}
