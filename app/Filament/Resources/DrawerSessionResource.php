<?php

namespace App\Filament\Resources;

use App\DTOs\CloseDrawerData;
use App\Enums\DrawerSessionStatus;
use App\Filament\Resources\DrawerSessionResource\Pages;
use App\Models\CashierDrawerSession;
use App\Services\DrawerSessionService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DrawerSessionResource extends Resource
{
    protected static ?string $model = CashierDrawerSession::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox';
    protected static string | \UnitEnum | null $navigationGroup = 'العمليات';
    protected static ?string $navigationLabel = 'جلسات الدرج';
    protected static ?string $modelLabel = 'جلسة درج';
    protected static ?string $pluralModelLabel = 'جلسات الدرج';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session_number')->label('رقم الجلسة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cashier.name')->label('الكاشير')->searchable(),
                Tables\Columns\TextColumn::make('shift.shift_number')->label('الوردية'),
                Tables\Columns\TextColumn::make('posDevice.name')->label('الجهاز'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn (DrawerSessionStatus $state) => $state === DrawerSessionStatus::Open ? 'success' : 'gray')
                    ->formatStateUsing(fn (DrawerSessionStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('opening_balance')->label('رصيد الفتح')->money('EGP'),
                Tables\Columns\TextColumn::make('closing_balance')->label('رصيد الإغلاق')->money('EGP')->placeholder('—'),
                Tables\Columns\TextColumn::make('cash_difference')->label('الفرق')->money('EGP')->placeholder('—')
                    ->color(fn ($state) => $state && (float) $state < 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('started_at')->label('البداية')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->label('النهاية')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(collect(DrawerSessionStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('cashier_id')->label('الكاشير')
                    ->relationship('cashier', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('shift_id')->label('الوردية')
                    ->relationship('shift', 'shift_number')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('pos_device_id')->label('الجهاز')
                    ->relationship('posDevice', 'name'),
                Tables\Filters\Filter::make('started_at')
                    ->label('نطاق التاريخ')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('started_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('started_at', '<=', $date));
                    }),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\Action::make('closeDrawer')
                    ->label('إغلاق الدرج')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CashierDrawerSession $record) => $record->isOpen() && (
                        auth()->id() === $record->cashier_id ||
                        auth()->user()?->hasPermission('drawers.close')
                    ))
                    ->form([
                        Forms\Components\TextInput::make('actual_cash')->label('النقد الفعلي')->numeric()->required()->prefix('ج.م'),
                        Forms\Components\Textarea::make('notes')->label('ملاحظات'),
                    ])
                    ->action(function (CashierDrawerSession $record, array $data) {
                        try {
                            app(DrawerSessionService::class)->close(
                                session: $record,
                                actor: auth()->user(),
                                data: new CloseDrawerData(
                                    actualCash: (float) $data['actual_cash'],
                                    closedBy: auth()->id(),
                                    notes: $data['notes'] ?? null,
                                ),
                            );
                            Notification::make()->title('تم إغلاق الدرج بنجاح')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('تفاصيل الجلسة')->schema([
                Infolists\Components\TextEntry::make('session_number')->label('رقم الجلسة'),
                Infolists\Components\TextEntry::make('cashier.name')->label('الكاشير'),
                Infolists\Components\TextEntry::make('shift.shift_number')->label('الوردية'),
                Infolists\Components\TextEntry::make('posDevice.name')->label('الجهاز'),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()->formatStateUsing(fn (DrawerSessionStatus $state) => $state->label()),
                Infolists\Components\TextEntry::make('opener.name')->label('فتح بواسطة'),
                Infolists\Components\TextEntry::make('closer.name')->label('أغلق بواسطة')->placeholder('—'),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('الأرصدة')->schema([
                Infolists\Components\TextEntry::make('opening_balance')->label('رصيد الفتح')->money('EGP'),
                Infolists\Components\TextEntry::make('expected_balance')->label('الرصيد المتوقع')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('closing_balance')->label('رصيد الإغلاق')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('cash_difference')->label('الفرق')->money('EGP')->placeholder('—'),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('التوقيتات')->schema([
                Infolists\Components\TextEntry::make('started_at')->label('البداية')->dateTime(),
                Infolists\Components\TextEntry::make('ended_at')->label('النهاية')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—'),
            ])->columns(3),
        ]);
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrawerSessions::route('/'),
            'view'  => Pages\ViewDrawerSession::route('/{record}'),
        ];
    }
}
