<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryTransferResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryTransfer;
use App\Support\BusinessTime;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryTransferResource extends Resource
{
    protected static ?string $model = InventoryTransfer::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static string | \UnitEnum | null $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'تحويلات المخزون';
    protected static ?string $modelLabel = 'تحويل مخزون';
    protected static ?string $pluralModelLabel = 'تحويلات المخزون';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            \Filament\Schemas\Components\Section::make('بيانات التحويل')->schema([
                Forms\Components\Select::make('source_location_id')
                    ->label('من موقع')
                    ->relationship('sourceLocation', 'name', fn ($query) => $query->active()->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => InventoryLocation::query()->where('type', 'warehouse')->where('is_active', true)->value('id'))
                    ->disabled(fn (?InventoryTransfer $record) => $record && !$record->isDraft()),
                Forms\Components\Select::make('destination_location_id')
                    ->label('إلى موقع')
                    ->relationship('destinationLocation', 'name', fn ($query) => $query->active()->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => InventoryLocation::query()->where('type', 'restaurant')->where('is_active', true)->value('id'))
                    ->disabled(fn (?InventoryTransfer $record) => $record && !$record->isDraft()),
                Forms\Components\Placeholder::make('status_label')
                    ->label('الحالة')
                    ->content(fn (?InventoryTransfer $record) => match($record?->status) {
                        'sent' => 'تم الإرسال',
                        'received' => 'تم الاستلام',
                        'cancelled' => 'ملغي',
                        default => 'مسودة',
                    }),
                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull()
                    ->disabled(fn (?InventoryTransfer $record) => $record && !$record->isDraft()),
            ])->columns(3),
            \Filament\Schemas\Components\Section::make('بنود التحويل')->schema([
                Forms\Components\Repeater::make('items')
                    ->relationship()
                    ->label('البنود')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->disabled(fn (?InventoryTransfer $record) => $record && !$record->isDraft())
                    ->schema([
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
                                $set('unit_cost', $item?->unit_cost);
                            }),
                        Forms\Components\TextInput::make('unit')
                            ->label('الوحدة')
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('quantity_sent')
                            ->label('الكمية المرسلة')
                            ->numeric()
                            ->required()
                            ->minValue(0.001),
                        Forms\Components\TextInput::make('quantity_received')
                            ->label('الكمية المستلمة')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('اتركها 0 ليتم استلام كامل الكمية المرسلة.')
                            ->hidden(fn (?InventoryTransfer $record) => !$record || $record->isDraft()),
                        Forms\Components\TextInput::make('unit_cost')
                            ->label('تكلفة الوحدة')
                            ->numeric()
                            ->prefix('ج.م')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $businessTimezone = BusinessTime::timezone();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->label('رقم التحويل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sourceLocation.name')
                    ->label('من')
                    ->sortable(),
                Tables\Columns\TextColumn::make('destinationLocation.name')
                    ->label('إلى')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'warning',
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'sent' => 'تم الإرسال',
                        'received' => 'تم الاستلام',
                        'cancelled' => 'ملغي',
                        default => 'مسودة',
                    }),
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('مطلوب بواسطة')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transferredBy.name')
                    ->label('تم النقل بواسطة')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label('تم الاستلام بواسطة')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime()
                    ->timezone($businessTimezone)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('تاريخ الاستلام')
                    ->dateTime()
                    ->timezone($businessTimezone)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'sent' => 'تم الإرسال',
                        'received' => 'تم الاستلام',
                        'cancelled' => 'ملغي',
                    ]),
                Tables\Filters\SelectFilter::make('source_location_id')
                    ->label('من موقع')
                    ->relationship('sourceLocation', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('destination_location_id')
                    ->label('إلى موقع')
                    ->relationship('destinationLocation', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $infolist): Schema
    {
        $businessTimezone = BusinessTime::timezone();

        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('بيانات التحويل')->schema([
                Infolists\Components\TextEntry::make('transfer_number')->label('رقم التحويل'),
                Infolists\Components\TextEntry::make('sourceLocation.name')->label('من موقع'),
                Infolists\Components\TextEntry::make('destinationLocation.name')->label('إلى موقع'),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'sent' => 'تم الإرسال',
                        'received' => 'تم الاستلام',
                        'cancelled' => 'ملغي',
                        default => 'مسودة',
                    }),
                Infolists\Components\TextEntry::make('requester.name')->label('مطلوب بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('approver.name')->label('اعتمد بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('transferredBy.name')->label('نقل بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('receivedBy.name')->label('استلم بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('approved_at')->label('وقت الاعتماد')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('sent_at')->label('وقت الإرسال')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('received_at')->label('وقت الاستلام')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—')->columnSpanFull(),
            ])->columns(4),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryTransfers::route('/'),
            'create' => Pages\CreateInventoryTransfer::route('/create'),
            'edit' => Pages\EditInventoryTransfer::route('/{record}/edit'),
            'view' => Pages\ViewInventoryTransfer::route('/{record}'),
        ];
    }
}
