<?php

namespace App\Filament\Resources;

use App\DTOs\CloseDrawerData;
use App\Enums\CashMovementType;
use App\Enums\DrawerSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\DrawerSessionResource\Pages;
use App\Models\CashierDrawerSession;
use App\Models\Order;
use App\Support\BusinessTime;
use App\Services\AdminActivityLogService;
use App\Services\DrawerSessionService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas\Schema;
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
        $businessTimezone = BusinessTime::timezone();

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
                Tables\Columns\TextColumn::make('started_at')->label('البداية')->dateTime()->timezone($businessTimezone)->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->label('النهاية')->dateTime()->timezone($businessTimezone)->placeholder('—'),
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
                    ->query(fn ($query, array $data) => BusinessTime::applyUtcDateRange(
                        $query,
                        $data['from'] ?? null,
                        $data['until'] ?? null,
                        'started_at',
                    )),
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
                            app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record, $data): void {
                                app(DrawerSessionService::class)->close(
                                    session: $record,
                                    actor: auth()->user(),
                                    data: new CloseDrawerData(
                                        actualCash: (float) $data['actual_cash'],
                                        closedBy: auth()->id(),
                                        notes: $data['notes'] ?? null,
                                    ),
                                );
                            });
                            $record->refresh();
                            app(AdminActivityLogService::class)->logAction(
                                action: 'closed',
                                subject: $record,
                                description: 'تم إغلاق جلسة درج من لوحة الإدارة.',
                                newValues: [
                                    'closing_balance' => $record->closing_balance,
                                    'expected_balance' => $record->expected_balance,
                                    'cash_difference' => $record->cash_difference,
                                ],
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
        $businessTimezone = BusinessTime::timezone();

        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('تفاصيل الجلسة')->schema([
                Infolists\Components\TextEntry::make('session_number')->label('رقم الجلسة'),
                Infolists\Components\TextEntry::make('cashier.name')->label('الكاشير'),
                Infolists\Components\TextEntry::make('shift.shift_number')->label('الوردية'),
                Infolists\Components\TextEntry::make('posDevice.name')->label('الجهاز'),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()->formatStateUsing(fn (DrawerSessionStatus $state) => $state->label()),
                Infolists\Components\TextEntry::make('opener.name')->label('فتح بواسطة'),
                Infolists\Components\TextEntry::make('closer.name')->label('أغلق بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('orders_count')
                    ->label('عدد الطلبات')
                    ->state(fn (CashierDrawerSession $record) => $record->reportableOrdersCollection()->count()),
                Infolists\Components\TextEntry::make('paid_orders_count')
                    ->label('طلبات مدفوعة')
                    ->state(fn (CashierDrawerSession $record) => $record->reportableOrdersCollection()->where('payment_status', PaymentStatus::Paid)->count()),
                Infolists\Components\TextEntry::make('open_orders_count')
                    ->label('طلبات غير مكتملة')
                    ->state(fn (CashierDrawerSession $record) => $record->reportableOrdersCollection()->where('payment_status', '!=', PaymentStatus::Paid)->count()),
                Infolists\Components\TextEntry::make('duration')
                    ->label('مدة الجلسة')
                    ->state(fn (CashierDrawerSession $record) => $record->ended_at
                        ? $record->started_at?->diffForHumans($record->ended_at, true)
                        : $record->started_at?->diffForHumans(BusinessTime::now(), true)),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('المؤشرات المالية')->schema([
                Infolists\Components\TextEntry::make('opening_balance')->label('رصيد الفتح')->money('EGP'),
                Infolists\Components\TextEntry::make('expected_balance')
                    ->label('الرصيد المتوقع')
                    ->state(fn (CashierDrawerSession $record) => $record->calculateExpectedBalance())
                    ->money('EGP')
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('closing_balance')->label('رصيد الإغلاق')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('cash_difference')->label('الفرق')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('cash_sales_total')
                    ->label('مبيعات نقدية')
                    ->state(fn (CashierDrawerSession $record) => $record->reportableCashSalesTotal())
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('non_cash_sales_total')
                    ->label('مبيعات غير نقدية')
                    ->state(fn (CashierDrawerSession $record) => $record->reportableNonCashSalesTotal())
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('refund_total')
                    ->label('استرجاعات نقدية')
                    ->state(fn (CashierDrawerSession $record) => $record->refundCashTotal())
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('cash_in_total')
                    ->label('إيداعات نقدية')
                    ->state(fn (CashierDrawerSession $record) => $record->manualCashInTotal())
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('cash_out_total')
                    ->label('سحوبات نقدية')
                    ->state(fn (CashierDrawerSession $record) => $record->manualCashOutTotal())
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('expenses_total')
                    ->label('مصروفات نقدية')
                    ->state(fn (CashierDrawerSession $record) => round((float) $record->expenses->sum('amount'), 2))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('gross_sales_total')
                    ->label('إجمالي مبيعات الطلبات')
                    ->state(fn (CashierDrawerSession $record) => round((float) $record->revenueOrdersCollection()->sum('total'), 2))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('avg_ticket')
                    ->label('متوسط قيمة الطلب')
                    ->state(function (CashierDrawerSession $record): float {
                        $orders = $record->revenueOrdersCollection();
                        $count = max(1, $orders->count());

                        return round((float) $orders->sum('total') / $count, 2);
                    })
                    ->money('EGP'),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('التوقيتات')->schema([
                Infolists\Components\TextEntry::make('started_at')->label('البداية')->dateTime()->timezone($businessTimezone),
                Infolists\Components\TextEntry::make('ended_at')->label('النهاية')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—'),
            ])->columns(3),
            \Filament\Schemas\Components\Section::make('الطلبات المرتبطة')->schema([
                Infolists\Components\TextEntry::make('orders_empty_state')
                    ->label('')
                    ->state('لا توجد طلبات مرتبطة بهذه الجلسة حتى الآن.')
                    ->visible(fn (CashierDrawerSession $record) => $record->orders->isEmpty()),
                Infolists\Components\RepeatableEntry::make('orders')->label('')->schema([
                    Infolists\Components\TextEntry::make('order_number')->label('رقم الطلب'),
                    Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                        ->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                    Infolists\Components\TextEntry::make('payment_status')->label('الدفع')->badge()
                        ->formatStateUsing(fn (PaymentStatus $state) => $state->label()),
                    Infolists\Components\TextEntry::make('type_label')->label('النوع'),
                    Infolists\Components\TextEntry::make('source_label')->label('المصدر'),
                    Infolists\Components\TextEntry::make('customer_name')->label('العميل')->placeholder('—'),
                    Infolists\Components\TextEntry::make('total')->label('الإجمالي')->money('EGP'),
                    Infolists\Components\TextEntry::make('paid_amount')
                        ->label('المدفوع')
                        ->state(fn (Order $record) => $record->reportablePaidAmount())
                        ->money('EGP'),
                    Infolists\Components\TextEntry::make('change_amount')->label('الباقي')->money('EGP'),
                    Infolists\Components\TextEntry::make('payment_methods_summary')
                        ->label('طرق الدفع')
                        ->state(fn (Order $record) => collect($record->reportablePaymentBreakdown())
                            ->map(fn ($amount, $method) => PaymentMethod::from($method)->label() . ' ' . number_format((float) $amount, 2) . ' ج.م')
                            ->implode('، '))
                        ->placeholder('—')
                        ->columnSpan(2),
                    Infolists\Components\TextEntry::make('items_count')
                        ->label('عدد الأصناف')
                        ->state(fn (Order $record) => $record->items->count()),
                    Infolists\Components\TextEntry::make('created_at')->label('وقت الإنشاء')->dateTime()->timezone($businessTimezone),
                ])
                    ->columns(4)
                    ->visible(fn (CashierDrawerSession $record) => $record->orders->isNotEmpty()),
            ]),
            \Filament\Schemas\Components\Section::make('الحركات النقدية')->schema([
                Infolists\Components\TextEntry::make('cash_movements_empty_state')
                    ->label('')
                    ->state('لا توجد حركات نقدية مسجلة على هذه الجلسة.')
                    ->visible(fn (CashierDrawerSession $record) => $record->cashMovements->isEmpty()),
                Infolists\Components\RepeatableEntry::make('cashMovements')->label('')->schema([
                    Infolists\Components\TextEntry::make('type')->label('النوع')
                        ->formatStateUsing(fn (CashMovementType $state) => $state->label()),
                    Infolists\Components\TextEntry::make('direction')->label('الاتجاه')
                        ->formatStateUsing(fn ($state) => $state->label()),
                    Infolists\Components\TextEntry::make('amount')->label('المبلغ')->money('EGP'),
                    Infolists\Components\TextEntry::make('reference_type')->label('نوع المرجع')->placeholder('—'),
                    Infolists\Components\TextEntry::make('reference_id')->label('رقم المرجع')->placeholder('—'),
                    Infolists\Components\TextEntry::make('performer.name')->label('نفذ بواسطة')->placeholder('—'),
                    Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—')->columnSpan(2),
                    Infolists\Components\TextEntry::make('created_at')->label('التوقيت')->dateTime()->timezone($businessTimezone),
                ])
                    ->columns(4)
                    ->visible(fn (CashierDrawerSession $record) => $record->cashMovements->isNotEmpty()),
            ]),
            \Filament\Schemas\Components\Section::make('المصروفات المسجلة على الجلسة')->schema([
                Infolists\Components\TextEntry::make('expenses_empty_state')
                    ->label('')
                    ->state('لا توجد مصروفات مرتبطة بهذه الجلسة.')
                    ->visible(fn (CashierDrawerSession $record) => $record->expenses->isEmpty()),
                Infolists\Components\RepeatableEntry::make('expenses')->label('')->schema([
                    Infolists\Components\TextEntry::make('expense_number')->label('رقم المصروف'),
                    Infolists\Components\TextEntry::make('category.name')->label('الفئة')->placeholder('—'),
                    Infolists\Components\TextEntry::make('amount')->label('المبلغ')->money('EGP'),
                    Infolists\Components\TextEntry::make('payment_method')->label('طريقة الدفع')->placeholder('—'),
                    Infolists\Components\TextEntry::make('receipt_number')->label('رقم الإيصال')->placeholder('—'),
                    Infolists\Components\TextEntry::make('description')->label('الوصف')->columnSpan(2),
                    Infolists\Components\TextEntry::make('approved_at')->label('وقت الاعتماد')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                    Infolists\Components\TextEntry::make('approver.name')->label('اعتمد بواسطة')->placeholder('—'),
                ])
                    ->columns(4)
                    ->visible(fn (CashierDrawerSession $record) => $record->expenses->isNotEmpty()),
            ]),
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
