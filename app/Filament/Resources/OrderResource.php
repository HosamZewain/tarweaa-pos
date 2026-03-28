<?php

namespace App\Filament\Resources;

use App\Enums\OrderSource;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\DiscountLog;
use App\Models\Order;
use App\Services\AdminActivityLogService;
use App\Services\OrderLifecycleService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \UnitEnum | null $navigationGroup = 'العمليات';
    protected static ?string $navigationLabel = 'الطلبات';
    protected static ?string $modelLabel = 'طلب';
    protected static ?string $pluralModelLabel = 'الطلبات';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // Orders are created only via POS
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('رقم الطلب')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge()
                    ->formatStateUsing(fn (OrderType $state) => $state->label()),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn (OrderStatus $state) => match($state) {
                        OrderStatus::Pending => 'warning',
                        OrderStatus::Confirmed, OrderStatus::Preparing => 'info',
                        OrderStatus::Ready => 'success',
                        OrderStatus::Delivered => 'success',
                        OrderStatus::Cancelled => 'danger',
                        OrderStatus::Refunded => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('source')->label('المصدر')
                    ->formatStateUsing(fn (OrderSource $state) => $state->label()),
                Tables\Columns\TextColumn::make('cashier.name')->label('الكاشير')->searchable(),
                Tables\Columns\TextColumn::make('discount_amount')->label('الخصم')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('total')->label('المجموع')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('payment_status')->label('الدفع')->badge()
                    ->color(fn (PaymentStatus $state) => match($state) {
                        PaymentStatus::Paid => 'success',
                        PaymentStatus::Partial => 'warning',
                        PaymentStatus::Unpaid => 'danger',
                        PaymentStatus::Refunded => 'gray',
                    })
                    ->formatStateUsing(fn (PaymentStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('type')->label('النوع')
                    ->options(collect(OrderType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                Tables\Filters\SelectFilter::make('source')->label('المصدر')
                    ->options(collect(OrderSource::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('payment_status')->label('حالة الدفع')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('cashier_id')->label('الكاشير')
                    ->relationship('cashier', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('shift_id')->label('الوردية')
                    ->relationship('shift', 'shift_number')->searchable()->preload(),
                Tables\Filters\Filter::make('discounted')
                    ->label('الطلبات المخصومة فقط')
                    ->query(fn ($query) => $query->where('discount_amount', '>', 0)),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\Action::make('cancel')
                    ->label('إلغاء')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء الطلب')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('سبب الإلغاء')->required(),
                    ])
                    ->visible(fn (Order $record) => $record->isCancellable() && auth()->user()?->hasPermission('orders.cancel'))
                    ->action(function (Order $record, array $data) {
                        abort_unless(auth()->user()?->hasPermission('orders.cancel'), 403);
                        app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record, $data): void {
                            app(OrderLifecycleService::class)->cancel($record, auth()->user(), $data['reason']);
                        });
                        $record->refresh();
                        app(AdminActivityLogService::class)->logAction(
                            action: 'cancelled',
                            subject: $record,
                            description: 'تم إلغاء طلب من لوحة الإدارة.',
                            newValues: [
                                'status' => $record->status,
                                'cancellation_reason' => $data['reason'],
                            ],
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist->schema([
            \Filament\Schemas\Components\Section::make('تفاصيل الطلب')->schema([
                Infolists\Components\TextEntry::make('order_number')->label('رقم الطلب'),
                Infolists\Components\TextEntry::make('type')->label('النوع')->formatStateUsing(fn (OrderType $state) => $state->label()),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                Infolists\Components\TextEntry::make('source')->label('المصدر')->formatStateUsing(fn (OrderSource $state) => $state->label()),
                Infolists\Components\TextEntry::make('cashier.name')->label('الكاشير'),
                Infolists\Components\TextEntry::make('shift.shift_number')->label('الوردية'),
                Infolists\Components\TextEntry::make('drawerSession.session_number')->label('جلسة الدرج')->placeholder('—'),
                Infolists\Components\TextEntry::make('posDevice.name')->label('جهاز نقطة البيع')->placeholder('—'),
                Infolists\Components\TextEntry::make('customer_name')->label('العميل')->placeholder('—'),
                Infolists\Components\TextEntry::make('customer_phone')->label('هاتف العميل')->placeholder('—'),
                Infolists\Components\TextEntry::make('delivery_address')->label('عنوان التوصيل')->placeholder('—')->columnSpan(2),
                Infolists\Components\TextEntry::make('external_order_number')->label('رقم الطلب الخارجي')->placeholder('—'),
                Infolists\Components\TextEntry::make('external_order_id')->label('المعرف الخارجي')->placeholder('—'),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('المبالغ')->schema([
                Infolists\Components\TextEntry::make('subtotal')->label('المجموع الفرعي')->money('EGP'),
                Infolists\Components\TextEntry::make('discount_amount')->label('الخصم')->money('EGP'),
                Infolists\Components\TextEntry::make('tax_amount')->label('الضريبة')->money('EGP'),
                Infolists\Components\TextEntry::make('delivery_fee')->label('رسوم التوصيل')->money('EGP'),
                Infolists\Components\TextEntry::make('total')->label('الإجمالي')->money('EGP')->weight('bold'),
                Infolists\Components\TextEntry::make('paid_amount')->label('المدفوع')->money('EGP'),
                Infolists\Components\TextEntry::make('change_amount')->label('الباقي')->money('EGP'),
                Infolists\Components\TextEntry::make('payment_status')->label('حالة الدفع')->badge()->formatStateUsing(fn (PaymentStatus $state) => $state->label()),
            ])->columns(4),
            \Filament\Schemas\Components\Section::make('التوقيتات')->schema([
                Infolists\Components\TextEntry::make('created_at')->label('وقت الإنشاء')->dateTime(),
                Infolists\Components\TextEntry::make('confirmed_at')->label('وقت التأكيد')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('ready_at')->label('وقت الجاهزية')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('delivered_at')->label('وقت التسليم')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('cancelled_at')->label('وقت الإلغاء')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('cancellation_reason')->label('سبب الإلغاء')->placeholder('—'),
                Infolists\Components\TextEntry::make('refunded_at')->label('وقت الاسترجاع')->dateTime()->placeholder('—'),
                Infolists\Components\TextEntry::make('refunder.name')->label('تم الاسترجاع بواسطة')->placeholder('—'),
                Infolists\Components\TextEntry::make('refund_reason')->label('سبب الاسترجاع')->placeholder('—'),
            ])->columns(3),
            \Filament\Schemas\Components\Section::make('تفاصيل الخصم')->schema([
                Infolists\Components\TextEntry::make('discount_type')
                    ->label('نوع الخصم الحالي')
                    ->state(fn (Order $record) => $record->discount_type === 'percentage' ? 'نسبة مئوية' : ($record->discount_type === 'fixed' ? 'مبلغ ثابت' : null))
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('discount_value')
                    ->label('قيمة الخصم')
                    ->state(function (Order $record): ?string {
                        if ($record->discount_type === 'percentage' && $record->discount_value !== null) {
                            return number_format((float) $record->discount_value, 2) . '%';
                        }

                        if ($record->discount_type === 'fixed' && $record->discount_value !== null) {
                            return number_format((float) $record->discount_value, 2) . ' ج.م';
                        }

                        return null;
                    })
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('discount_amount')
                    ->label('قيمة الخصم الفعلية')
                    ->money('EGP'),
                Infolists\Components\TextEntry::make('latest_order_discount_requester')
                    ->label('طلب الخصم بواسطة')
                    ->state(fn (Order $record) => $record->latestOrderDiscountLog?->requestedBy?->name)
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('latest_order_discount_approver')
                    ->label('اعتمد / طبق الخصم')
                    ->state(fn (Order $record) => $record->latestOrderDiscountLog?->appliedBy?->name)
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('latest_order_discount_reason')
                    ->label('سبب الخصم')
                    ->state(fn (Order $record) => $record->latestOrderDiscountLog?->reason)
                    ->placeholder('—')
                    ->columnSpan(2),
                Infolists\Components\TextEntry::make('latest_order_discount_action')
                    ->label('آخر إجراء')
                    ->state(function (Order $record): ?string {
                        return match ($record->latestOrderDiscountLog?->action) {
                            'applied' => 'تم التطبيق',
                            'updated' => 'تم التحديث',
                            'removed' => 'تمت الإزالة',
                            'configured_on_create' => 'تمت التهيئة عند الإنشاء',
                            'backfilled_existing_order' => 'تم ترحيله من بيانات سابقة',
                            default => $record->latestOrderDiscountLog?->action,
                        };
                    })
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('latest_order_discount_created_at')
                    ->label('وقت اعتماد الخصم')
                    ->state(fn (Order $record) => $record->latestOrderDiscountLog?->created_at)
                    ->dateTime()
                    ->placeholder('—'),
            ])
                ->columns(4)
                ->visible(fn (Order $record) => (float) $record->discount_amount > 0 || $record->orderDiscountLogs->isNotEmpty()),
            \Filament\Schemas\Components\Section::make('الأصناف المطلوبة')->schema([
                Infolists\Components\RepeatableEntry::make('items')->label('')->schema([
                    Infolists\Components\TextEntry::make('item_name')->label('الصنف'),
                    Infolists\Components\TextEntry::make('variant_name')->label('الصنف الفرعي')->placeholder('—'),
                    Infolists\Components\TextEntry::make('quantity')->label('الكمية')->numeric(),
                    Infolists\Components\TextEntry::make('unit_price')->label('سعر الوحدة')->money('EGP'),
                    Infolists\Components\TextEntry::make('discount_amount')->label('خصم السطر')->money('EGP'),
                    Infolists\Components\TextEntry::make('total')->label('الإجمالي')->money('EGP'),
                    Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                        ->formatStateUsing(fn (?OrderItemStatus $state) => $state?->label() ?? '—'),
                    Infolists\Components\TextEntry::make('modifiers_summary')
                        ->label('الإضافات')
                        ->state(function ($record): ?string {
                            $modifiers = $record->modifiers ?? collect();

                            if ($modifiers->isEmpty()) {
                                return null;
                            }

                            return $modifiers->map(function ($modifier) {
                                $suffix = (int) $modifier->quantity > 1 ? ' × ' . $modifier->quantity : '';

                                return $modifier->modifier_name . $suffix;
                            })->implode('، ');
                        })
                        ->placeholder('—')
                        ->columnSpan(2),
                    Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—'),
                ])->columns(4),
            ]),
            \Filament\Schemas\Components\Section::make('المدفوعات')->schema([
                Infolists\Components\RepeatableEntry::make('payments')->label('')->schema([
                    Infolists\Components\TextEntry::make('payment_method')->label('طريقة الدفع')
                        ->formatStateUsing(fn (PaymentMethod|string|null $state) => $state instanceof PaymentMethod ? $state->label() : ($state ?: '—')),
                    Infolists\Components\TextEntry::make('amount')->label('المبلغ')->money('EGP'),
                    Infolists\Components\TextEntry::make('reference_number')->label('مرجع')->placeholder('—'),
                    Infolists\Components\TextEntry::make('notes')->label('ملاحظات الدفع')->placeholder('—'),
                    Infolists\Components\TextEntry::make('created_at')->label('التوقيت')->dateTime(),
                ])->columns(5),
            ]),
            \Filament\Schemas\Components\Section::make('سجل الخصومات')->schema([
                Infolists\Components\RepeatableEntry::make('orderDiscountLogs')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')->label('التوقيت')->dateTime(),
                        Infolists\Components\TextEntry::make('requestedBy.name')->label('طلب بواسطة')->placeholder('—'),
                        Infolists\Components\TextEntry::make('appliedBy.name')->label('اعتمد بواسطة')->placeholder('—'),
                        Infolists\Components\TextEntry::make('action')->label('الإجراء')
                            ->formatStateUsing(fn (?string $state) => match ($state) {
                                'applied' => 'تم التطبيق',
                                'updated' => 'تم التحديث',
                                'removed' => 'تمت الإزالة',
                                'configured_on_create' => 'تمت التهيئة عند الإنشاء',
                                'backfilled_existing_order' => 'تم ترحيله من بيانات سابقة',
                                default => $state ?: '—',
                            }),
                        Infolists\Components\TextEntry::make('discount_type')->label('نوع الخصم')
                            ->formatStateUsing(fn (?string $state) => $state === 'percentage' ? 'نسبة مئوية' : ($state === 'fixed' ? 'مبلغ ثابت' : '—')),
                        Infolists\Components\TextEntry::make('discount_value')->label('القيمة')
                            ->state(function (DiscountLog $record): string {
                                if ($record->discount_type === 'percentage') {
                                    return number_format((float) $record->discount_value, 2) . '%';
                                }

                                return number_format((float) $record->discount_value, 2) . ' ج.م';
                            }),
                        Infolists\Components\TextEntry::make('discount_amount')->label('الخصم الفعلي')->money('EGP'),
                        Infolists\Components\TextEntry::make('reason')->label('السبب')->placeholder('—')->columnSpan(2),
                    ])
                    ->columns(4),
            ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (Order $record) => $record->orderDiscountLogs->isNotEmpty()),
            \Filament\Schemas\Components\Section::make('ملاحظات الطلب')->schema([
                Infolists\Components\TextEntry::make('notes')->label('')->placeholder('لا توجد ملاحظات')->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ]);
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([]); // Read-only
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view'  => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
