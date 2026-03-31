<?php

namespace App\Filament\Resources;

use App\DTOs\CloseShiftData;
use App\DTOs\OpenShiftData;
use App\Enums\CashMovementType;
use App\Enums\DrawerSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Filament\Resources\ShiftResource\Pages;
use App\Models\CashierDrawerSession;
use App\Models\Order;
use App\Models\Shift;
use App\Support\BusinessTime;
use App\Services\AdminActivityLogService;
use App\Services\ShiftService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas\Schema;
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
        $businessTimezone = BusinessTime::timezone();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shift_number')->label('رقم الوردية')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn (ShiftStatus $state) => $state === ShiftStatus::Open ? 'success' : 'gray')
                    ->formatStateUsing(fn (ShiftStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('opener.name')->label('فتح بواسطة'),
                Tables\Columns\TextColumn::make('closer.name')->label('أغلق بواسطة')->placeholder('—'),
                Tables\Columns\TextColumn::make('started_at')->label('بداية')->dateTime()->timezone($businessTimezone)->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->label('نهاية')->dateTime()->timezone($businessTimezone)->placeholder('—'),
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
                    ->query(fn ($query, array $data) => BusinessTime::applyUtcDateRange(
                        $query,
                        $data['from'] ?? null,
                        $data['until'] ?? null,
                        'started_at',
                    )),
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
                            app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record, $data): void {
                                app(ShiftService::class)->close(
                                    $record,
                                    auth()->user(),
                                    CloseShiftData::fromArray($data),
                                );
                            });
                            $record->refresh();
                            app(AdminActivityLogService::class)->logAction(
                                action: 'closed',
                                subject: $record,
                                description: 'تم إغلاق وردية من لوحة الإدارة.',
                                newValues: [
                                    'actual_cash' => $record->actual_cash,
                                    'expected_cash' => $record->expected_cash,
                                    'cash_difference' => $record->cash_difference,
                                ],
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
                            $shift = app(AdminActivityLogService::class)->withoutModelLogging(function () use ($data) {
                                return app(ShiftService::class)->open(
                                    auth()->user(),
                                    OpenShiftData::fromArray($data),
                                );
                            });
                            app(AdminActivityLogService::class)->logAction(
                                action: 'opened',
                                subject: $shift,
                                description: 'تم فتح وردية جديدة من لوحة الإدارة.',
                                newValues: [
                                    'notes' => $shift->notes,
                                    'started_at' => $shift->started_at,
                                ],
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
        $businessTimezone = BusinessTime::timezone();

        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('تفاصيل الوردية')->schema([
                Infolists\Components\TextEntry::make('shift_number')->label('رقم الوردية'),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()->formatStateUsing(fn (ShiftStatus $state) => $state->label()),
                Infolists\Components\TextEntry::make('opener.name')->label('فتح بواسطة'),
                Infolists\Components\TextEntry::make('closer.name')->label('أغلق بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('started_at')->label('البداية')->dateTime()->timezone($businessTimezone),
                Infolists\Components\TextEntry::make('ended_at')->label('النهاية')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—'),
                Infolists\Components\TextEntry::make('duration')
                    ->label('مدة الوردية')
                    ->state(fn (Shift $record) => $record->durationLabel()),
                Infolists\Components\TextEntry::make('drawer_count')
                    ->label('عدد الأدراج')
                    ->state(fn (Shift $record) => $record->drawerSessions->count()),
                Infolists\Components\TextEntry::make('open_drawers_count')
                    ->label('أدراج مفتوحة')
                    ->state(fn (Shift $record) => $record->drawerSessions->where('status', DrawerSessionStatus::Open)->count()),
                Infolists\Components\TextEntry::make('cashiers_count')
                    ->label('عدد الكاشير')
                    ->state(fn (Shift $record) => $record->drawerSessions->pluck('cashier_id')->unique()->count()),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('الإحصاءات التشغيلية')->schema([
                Infolists\Components\TextEntry::make('total_orders')
                    ->label('إجمالي الطلبات')
                    ->state(fn (Shift $record) => $record->reportableOrdersCollection()->count()),
                Infolists\Components\TextEntry::make('paid_orders')
                    ->label('طلبات مدفوعة')
                    ->state(fn (Shift $record) => $record->reportableOrdersCollection()->where('payment_status', PaymentStatus::Paid)->count()),
                Infolists\Components\TextEntry::make('pending_orders')
                    ->label('طلبات غير مدفوعة')
                    ->state(fn (Shift $record) => $record->reportableOrdersCollection()->where('payment_status', '!=', PaymentStatus::Paid)->count()),
                Infolists\Components\TextEntry::make('cancelled_orders')
                    ->label('طلبات ملغاة')
                    ->state(fn (Shift $record) => $record->cancelledOrdersCollection()->count()),
                Infolists\Components\TextEntry::make('confirmed_orders')
                    ->label('طلبات مؤكدة')
                    ->state(fn (Shift $record) => $record->reportableOrdersCollection()->where('status', OrderStatus::Confirmed)->count()),
                Infolists\Components\TextEntry::make('preparing_orders')
                    ->label('طلبات قيد التحضير')
                    ->state(fn (Shift $record) => $record->reportableOrdersCollection()->where('status', OrderStatus::Preparing)->count()),
                Infolists\Components\TextEntry::make('ready_orders')
                    ->label('طلبات جاهزة')
                    ->state(fn (Shift $record) => $record->reportableOrdersCollection()->where('status', OrderStatus::Ready)->count()),
                Infolists\Components\TextEntry::make('avg_order_value')
                    ->label('متوسط قيمة الطلب')
                    ->state(function (Shift $record): float {
                        $orders = $record->reportableOrdersCollection();
                        $count = max(1, $orders->count());

                        return round((float) $orders->sum('total') / $count, 2);
                    })
                    ->money('EGP'),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('الملخص المالي')->schema([
                Infolists\Components\TextEntry::make('gross_revenue')
                    ->label('إجمالي المبيعات')
                    ->state(fn (Shift $record) => round((float) $record->reportableOrdersCollection()->sum('total'), 2))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('cash_sales')
                    ->label('مبيعات نقدية')
                    ->state(fn (Shift $record) => round(
                        (float) $record->reportableOrdersCollection()->flatMap->payments->where('payment_method', PaymentMethod::Cash)->sum('amount'),
                        2
                    ))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('non_cash_sales')
                    ->label('مبيعات غير نقدية')
                    ->state(fn (Shift $record) => round(
                        (float) $record->reportableOrdersCollection()->flatMap->payments->reject(
                            fn ($payment) => $payment->payment_method === PaymentMethod::Cash
                        )->sum('amount'),
                        2
                    ))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('total_discounts')
                    ->label('إجمالي الخصومات')
                    ->state(fn (Shift $record) => round((float) $record->reportableOrdersCollection()->sum('discount_amount'), 2))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('total_tax')
                    ->label('إجمالي الضريبة')
                    ->state(fn (Shift $record) => round((float) $record->reportableOrdersCollection()->sum('tax_amount'), 2))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('delivery_fees')
                    ->label('رسوم التوصيل')
                    ->state(fn (Shift $record) => round((float) $record->reportableOrdersCollection()->sum('delivery_fee'), 2))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('refund_total')
                    ->label('إجمالي الاسترجاعات')
                    ->state(fn (Shift $record) => round((float) $record->reportableOrdersCollection()->sum('refund_amount'), 2))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('expected_cash')->label('النقد المتوقع')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('actual_cash')->label('النقد الفعلي')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('cash_difference')->label('الفرق')->money('EGP')->placeholder('—'),
                Infolists\Components\TextEntry::make('expenses_total')
                    ->label('إجمالي المصروفات')
                    ->state(fn (Shift $record) => round((float) $record->expenses->sum('amount'), 2))
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('net_cash')
                    ->label('صافي النقد بعد المصروفات')
                    ->state(fn (Shift $record) => round((float) ($record->actual_cash ?? 0) - (float) $record->expenses->sum('amount'), 2))
                    ->money('EGP'),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('جلسات الدرج ضمن الوردية')->schema([
                Infolists\Components\TextEntry::make('drawer_sessions_empty_state')
                    ->label('')
                    ->state('لا توجد جلسات درج مرتبطة بهذه الوردية.')
                    ->visible(fn (Shift $record) => $record->drawerSessions->isEmpty()),
                Infolists\Components\RepeatableEntry::make('drawerSessions')->label('')->schema([
                    Infolists\Components\TextEntry::make('session_number')->label('رقم الجلسة'),
                    Infolists\Components\TextEntry::make('cashier.name')->label('الكاشير'),
                    Infolists\Components\TextEntry::make('posDevice.name')->label('الجهاز'),
                    Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                        ->formatStateUsing(fn (DrawerSessionStatus $state) => $state->label()),
                    Infolists\Components\TextEntry::make('opening_balance')->label('رصيد الفتح')->money('EGP'),
                    Infolists\Components\TextEntry::make('expected_balance')->label('المتوقع')->money('EGP')->placeholder('—'),
                    Infolists\Components\TextEntry::make('closing_balance')->label('الإغلاق')->money('EGP')->placeholder('—'),
                    Infolists\Components\TextEntry::make('cash_difference')->label('الفرق')->money('EGP')->placeholder('—'),
                    Infolists\Components\TextEntry::make('orders_count')
                        ->label('الطلبات')
                        ->state(fn (CashierDrawerSession $record) => $record->reportableOrdersCollection()->count()),
                    Infolists\Components\TextEntry::make('cash_sales_total')
                        ->label('مبيعات نقدية')
                        ->state(fn (CashierDrawerSession $record) => round(
                            (float) $record->cashMovements->where('type', CashMovementType::Sale)->sum('amount'),
                            2
                        ))
                        ->money('EGP'),
                    Infolists\Components\TextEntry::make('started_at')->label('البداية')->dateTime()->timezone($businessTimezone),
                    Infolists\Components\TextEntry::make('ended_at')->label('النهاية')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                ])
                    ->columns(4)
                    ->visible(fn (Shift $record) => $record->drawerSessions->isNotEmpty()),
            ]),
            \Filament\Schemas\Components\Section::make('طلبات الوردية')->schema([
                Infolists\Components\TextEntry::make('orders_empty_state')
                    ->label('')
                    ->state('لا توجد طلبات مرتبطة بهذه الوردية حتى الآن.')
                    ->visible(fn (Shift $record) => $record->orders->isEmpty()),
                Infolists\Components\RepeatableEntry::make('orders')->label('')->schema([
                    Infolists\Components\TextEntry::make('order_number')->label('رقم الطلب'),
                    Infolists\Components\TextEntry::make('cashier.name')->label('الكاشير')->placeholder('—'),
                    Infolists\Components\TextEntry::make('drawerSession.session_number')->label('جلسة الدرج')->placeholder('—'),
                    Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                        ->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                    Infolists\Components\TextEntry::make('payment_status')->label('الدفع')->badge()
                        ->formatStateUsing(fn (PaymentStatus $state) => $state->label()),
                    Infolists\Components\TextEntry::make('total')->label('الإجمالي')->money('EGP'),
                    Infolists\Components\TextEntry::make('paid_amount')->label('المدفوع')->money('EGP'),
                    Infolists\Components\TextEntry::make('change_amount')->label('الباقي')->money('EGP'),
                    Infolists\Components\TextEntry::make('payment_methods_summary')
                        ->label('طرق الدفع')
                        ->state(fn (Order $record) => $record->payments
                            ->map(fn ($payment) => $payment->payment_method->label() . ' ' . number_format((float) $payment->amount, 2) . ' ج.م')
                            ->implode('، '))
                        ->placeholder('—')
                        ->columnSpan(2),
                    Infolists\Components\TextEntry::make('customer_name')->label('العميل')->placeholder('—'),
                    Infolists\Components\TextEntry::make('created_at')->label('وقت الإنشاء')->dateTime()->timezone($businessTimezone),
                ])
                    ->columns(4)
                    ->visible(fn (Shift $record) => $record->orders->isNotEmpty()),
            ]),
            \Filament\Schemas\Components\Section::make('المصروفات المسجلة على الوردية')->schema([
                Infolists\Components\TextEntry::make('expenses_empty_state')
                    ->label('')
                    ->state('لا توجد مصروفات مسجلة على هذه الوردية.')
                    ->visible(fn (Shift $record) => $record->expenses->isEmpty()),
                Infolists\Components\RepeatableEntry::make('expenses')->label('')->schema([
                    Infolists\Components\TextEntry::make('expense_number')->label('رقم المصروف'),
                    Infolists\Components\TextEntry::make('category.name')->label('الفئة')->placeholder('—'),
                    Infolists\Components\TextEntry::make('drawerSession.session_number')->label('جلسة الدرج')->placeholder('—'),
                    Infolists\Components\TextEntry::make('amount')->label('المبلغ')->money('EGP'),
                    Infolists\Components\TextEntry::make('payment_method')->label('طريقة الدفع')->placeholder('—'),
                    Infolists\Components\TextEntry::make('receipt_number')->label('رقم الإيصال')->placeholder('—'),
                    Infolists\Components\TextEntry::make('description')->label('الوصف')->columnSpan(2),
                    Infolists\Components\TextEntry::make('approved_at')->label('وقت الاعتماد')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                    Infolists\Components\TextEntry::make('approver.name')->label('اعتمد بواسطة')->placeholder('—'),
                ])
                    ->columns(4)
                    ->visible(fn (Shift $record) => $record->expenses->isNotEmpty()),
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
            'index' => Pages\ListShifts::route('/'),
            'view'  => Pages\ViewShift::route('/{record}'),
        ];
    }
}
