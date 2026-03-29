<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminActivityLogResource\Pages;
use App\Models\AdminActivityLog;
use App\Support\BusinessTime;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AdminActivityLogResource extends Resource
{
    protected static ?string $model = AdminActivityLog::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string | \UnitEnum | null $navigationGroup = 'الإدارة';
    protected static ?string $navigationLabel = 'سجل النشاط الإداري';
    protected static ?string $modelLabel = 'سجل نشاط';
    protected static ?string $pluralModelLabel = 'سجل النشاط الإداري';
    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $businessTimezone = BusinessTime::timezone();

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime()
                    ->timezone($businessTimezone)
                    ->sortable(),
                Tables\Columns\TextColumn::make('actor.name')
                    ->label('المستخدم')
                    ->placeholder('النظام'),
                Tables\Columns\TextColumn::make('action_label')
                    ->label('الإجراء')
                    ->badge(),
                Tables\Columns\TextColumn::make('module')
                    ->label('الموديول')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('subject_label')
                    ->label('السجل المتأثر')
                    ->searchable()
                    ->wrap()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('description')
                    ->label('التفاصيل')
                    ->wrap()
                    ->limit(80),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('actor_user_id')
                    ->label('المستخدم')
                    ->relationship('actor', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('action')
                    ->label('الإجراء')
                    ->options(AdminActivityLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                Tables\Filters\SelectFilter::make('module')
                    ->label('الموديول')
                    ->options(AdminActivityLog::query()->distinct()->orderBy('module')->pluck('module', 'module')->filter()->all()),
                Tables\Filters\Filter::make('created_at')
                    ->label('نطاق التاريخ')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('من'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn ($query, array $data) => BusinessTime::applyUtcDateRange(
                        $query,
                        $data['from'] ?? null,
                        $data['until'] ?? null,
                    )),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        $businessTimezone = BusinessTime::timezone();

        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('ملخص النشاط')->schema([
                Infolists\Components\TextEntry::make('created_at')->label('الوقت')->dateTime()->timezone($businessTimezone),
                Infolists\Components\TextEntry::make('actor.name')->label('المستخدم')->placeholder('النظام'),
                Infolists\Components\TextEntry::make('action_label')->label('الإجراء')->badge(),
                Infolists\Components\TextEntry::make('module')->label('الموديول')->placeholder('—'),
                Infolists\Components\TextEntry::make('subject_label')->label('السجل المتأثر')->placeholder('—'),
                Infolists\Components\TextEntry::make('description')->label('الوصف')->placeholder('—')->columnSpanFull(),
            ])->columns(3),
            \Filament\Schemas\Components\Section::make('القيم السابقة')->schema([
                Infolists\Components\TextEntry::make('old_values')
                    ->label('')
                    ->state(fn (AdminActivityLog $record) => static::prettyJson($record->old_values))
                    ->placeholder('لا توجد قيم سابقة')
                    ->columnSpanFull(),
            ]),
            \Filament\Schemas\Components\Section::make('القيم الجديدة')->schema([
                Infolists\Components\TextEntry::make('new_values')
                    ->label('')
                    ->state(fn (AdminActivityLog $record) => static::prettyJson($record->new_values))
                    ->placeholder('لا توجد قيم جديدة')
                    ->columnSpanFull(),
            ]),
            \Filament\Schemas\Components\Section::make('بيانات إضافية')->schema([
                Infolists\Components\TextEntry::make('meta')
                    ->label('')
                    ->state(fn (AdminActivityLog $record) => static::prettyJson($record->meta))
                    ->placeholder('لا توجد بيانات إضافية')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminActivityLogs::route('/'),
            'view' => Pages\ViewAdminActivityLog::route('/{record}'),
        ];
    }

    private static function prettyJson(?array $payload): ?string
    {
        if (empty($payload)) {
            return null;
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
