<?php

namespace App\Filament\Resources;

use App\DTOs\CloseShiftData;
use App\DTOs\OpenShiftData;
use App\Enums\ShiftStatus;
use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use App\Services\ShiftService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';
    protected static string | \UnitEnum | null $navigationGroup = 'العمليات';
    protected static ?string $navigationLabel = 'الورديات';
    protected static ?string $modelLabel = 'وردية';
    protected static ?string $pluralModelLabel = 'الورديات';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // Opening shifts is via custom action
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shift_number')->label('رقم الوردية')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn (ShiftStatus $state) => $state === ShiftStatus::Open ? 'success' : 'gray')
                    ->formatStateUsing(fn (ShiftStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('opener.name')->label('فتح بواسطة'),
                Tables\Columns\TextColumn::make('closer.name')->label('أغلق بواسطة')->placeholder('—'),
                Tables\Columns\TextColumn::make('started_at')->label('بداية')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->label('نهاية')->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('expected_cash')->label('المتوقع')->money('EGP')->placeholder('—'),
                Tables\Columns\TextColumn::make('actual_cash')->label('الفعلي')->money('EGP')->placeholder('—'),
                Tables\Columns\TextColumn::make('cash_difference')->label('الفرق')->money('EGP')->placeholder('—')
                    ->color(fn ($state) => $state && (float) $state < 0 ? 'danger' : ($state && (float) $state > 0 ? 'warning' : 'success')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(collect(ShiftStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('opener_id')->label('فتح بواسطة')
                    ->relationship('opener', 'name')->searchable()->preload(),
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
                \Filament\Actions\Action::make('closeShift')
                    ->label('إغلاق الوردية')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إغلاق الوردية')
                    ->visible(fn (Shift $record) => $record->isOpen() && auth()->user()?->hasPermission('shifts.close'))
                    ->form([
                        Forms\Components\TextInput::make('actual_cash')->label('النقد الفعلي')->numeric()->required()->prefix('ج.م'),
                        Forms\Components\Textarea::make('notes')->label('ملاحظات'),
                    ])
                    ->action(function (Shift $record, array $data) {
                        try {
                            app(ShiftService::class)->close(
                                $record,
                                auth()->user(),
                                CloseShiftData::fromArray($data),
                            );
                            Notification::make()->title('تم إغلاق الوردية بنجاح')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('خطأ')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('openShift')
                    ->label('فتح وردية جديدة')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->hasPermission('shifts.open'))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')->label('ملاحظات'),
                    ])
                    ->action(function (array $data) {
                        try {
                            app(ShiftService::class)->open(
                                auth()->user(),
                                OpenShiftData::fromArray($data),
                            );
                            Notification::make()->title('تم فتح الوردية بنجاح')->success()->send();
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
            \Filament\Schemas\Components\Section::make('تفاصيل الوردية')->schema([
                Infolists\Components\TextEntry::make('shift_number')->label('رقم الوردية'),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()->formatStateUsing(fn (ShiftStatus $state) => $state->label()),
                Infolists\Components\TextEntry::make('opener.name')->label('فتح بواسطة'),
                Infolists\Components\TextEntry::make('closer.name')->label('أغلق بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('started_at')->label('البداية')->dateTime(),
                Infolists\Components\TextEntry::make('ended_at')->label('النهاية')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—'),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('الحسابات')->schema([
                Infolists\Components\TextEntry::make('expected_cash')->label('النقد المتوقع')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('actual_cash')->label('النقد الفعلي')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('cash_difference')->label('الفرق')->money('EGP')->placeholder('—'),
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
            'index' => Pages\ListShifts::route('/'),
            'view'  => Pages\ViewShift::route('/{record}'),
        ];
    }
}
