<?php

namespace App\Filament\Resources;

use App\DTOs\ProcessPaymentData;
use App\Enums\OrderSource;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\DiscountLog;
use App\Models\Order;
use App\Models\PaymentTerminal;
use App\Support\BusinessTime;
use App\Services\AdminActivityLogService;
use App\Services\OrderDeletionService;
use App\Services\OrderLifecycleService;
use App\Services\OrderPaymentService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
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
        $businessTimezone = BusinessTime::timezone();

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
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime()->timezone($businessTimezone)->sortable(),
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
                    ->query(fn ($query, array $data) => BusinessTime::applyUtcDateRange(
                        $query,
                        $data['from'] ?? null,
                        $data['until'] ?? null,
                    )),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                static::markReadyAction(),
                static::markDeliveredAction(),
                static::recordPaymentAction(),
                \Filament\Actions\Action::make('cancel')
                    ->label('إلغاء')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء الطلب')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('سبب الإلغاء')->required(),
                    ])
                    ->visible(fn (Order $record) => $record->isCancellable() && !$record->hasNonCashPayments() && auth()->user()?->hasPermission('orders.cancel'))
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
                \Filament\Actions\Action::make('safe_delete')
                    ->label('حذف آمن')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('حذف الطلب مع عكس العمليات')
                    ->modalDescription('سيتم عكس المخزون والمدفوعات النقدية والتسويات المرتبطة ثم إخفاء الطلب من النظام.')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('سبب الحذف')->required(),
                    ])
                    ->visible(fn (Order $record) => !$record->trashed() && !$record->hasNonCashPayments() && !$record->isCancellable() && auth()->user()?->hasPermission('orders.delete'))
                    ->action(function (Order $record, array $data) {
                        abort_unless(auth()->user()?->hasPermission('orders.delete'), 403);

                        app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record, $data): void {
                            app(OrderDeletionService::class)->deleteWithReversal($record, auth()->user(), $data['reason']);
                        });

                        app(AdminActivityLogService::class)->logAction(
                            action: 'deleted',
                            subject: $record,
                            description: 'تم حذف الطلب مع عكس العمليات من لوحة الإدارة.',
                            newValues: [
                                'status' => OrderStatus::Cancelled->value,
                                'deletion_reason' => $data['reason'],
                                'deleted_at' => now(),
                            ],
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $infolist): Schema
    {
        $businessTimezone = BusinessTime::timezone();

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
                Infolists\Components\TextEntry::make('created_at')->label('وقت الإنشاء')->dateTime()->timezone($businessTimezone),
                Infolists\Components\TextEntry::make('confirmed_at')->label('وقت التأكيد')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('ready_at')->label('وقت الجاهزية')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('delivered_at')->label('وقت التسليم')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('cancelled_at')->label('وقت الإلغاء')->dateTime()->timezone($businessTimezone)->placeholder('—'),
                Infolists\Components\TextEntry::make('cancellation_reason')->label('سبب الإلغاء')->placeholder('—'),
                Infolists\Components\TextEntry::make('refunded_at')->label('وقت الاسترجاع')->dateTime()->timezone($businessTimezone)->placeholder('—'),
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
                    ->timezone($businessTimezone)
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
                    Infolists\Components\TextEntry::make('created_at')->label('التوقيت')->dateTime()->timezone($businessTimezone),
                ])->columns(5),
            ]),
            \Filament\Schemas\Components\Section::make('سجل الخصومات')->schema([
                Infolists\Components\RepeatableEntry::make('orderDiscountLogs')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')->label('التوقيت')->dateTime()->timezone($businessTimezone),
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

    public static function recordPaymentAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('recordPayment')
            ->label('تسجيل دفع')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading('تسجيل دفعة على الطلب')
            ->modalDescription('سيتم تسجيل كامل المبلغ المتبقي على الطلب كدفعة فعلية، مع تحديث الدرج والتقارير والحالة بشكل طبيعي.')
            ->form([
                Forms\Components\Placeholder::make('remaining_payable')
                    ->label('المبلغ المتبقي')
                    ->content(fn (Order $record): string => number_format((float) $record->fresh(['settlement'])->remainingPayableAmount(), 2) . ' ج.م'),
                Forms\Components\Select::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $method) => [$method->value => $method->label()])->all())
                    ->default(PaymentMethod::Cash->value)
                    ->live()
                    ->required(),
                Forms\Components\Select::make('terminal_id')
                    ->label('جهاز الدفع')
                    ->options(fn (): array => PaymentTerminal::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Card->value)
                    ->required(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Card->value),
                Forms\Components\TextInput::make('reference_number')
                    ->label('المرجع / رقم العملية')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => in_array($get('payment_method'), [
                        PaymentMethod::Card->value,
                        PaymentMethod::TalabatPay->value,
                        PaymentMethod::InstaPay->value,
                    ], true))
                    ->required(fn (Get $get): bool => in_array($get('payment_method'), [
                        PaymentMethod::Card->value,
                        PaymentMethod::TalabatPay->value,
                        PaymentMethod::InstaPay->value,
                    ], true)),
                Forms\Components\Textarea::make('notes')
                    ->label('سبب تسجيل الدفع يدويًا')
                    ->required()
                    ->rows(3),
            ])
            ->visible(fn (Order $record): bool => static::canRecordManualPayment($record))
            ->action(function (Order $record, array $data): void {
                abort_unless(auth()->user()?->hasPermission('orders.record_payment'), 403);

                $order = $record->fresh(['settlement', 'drawerSession', 'payments']);
                $remainingAmount = round($order->remainingPayableAmount(), 2);
                $method = PaymentMethod::from((string) $data['payment_method']);

                $processedOrder = app(AdminActivityLogService::class)->withoutModelLogging(function () use ($order, $remainingAmount, $method, $data) {
                    return app(OrderPaymentService::class)->processPayment(
                        order: $order,
                        payments: [
                            new ProcessPaymentData(
                                method: $method,
                                amount: $remainingAmount,
                                terminalId: isset($data['terminal_id']) ? (int) $data['terminal_id'] : null,
                                referenceNumber: filled($data['reference_number'] ?? null) ? (string) $data['reference_number'] : null,
                            ),
                        ],
                        actorId: auth()->id(),
                    );
                });

                $terminal = isset($data['terminal_id']) && $data['terminal_id']
                    ? PaymentTerminal::query()->find((int) $data['terminal_id'])
                    : null;

                app(AdminActivityLogService::class)->logAction(
                    action: 'manual_payment_recorded',
                    subject: $processedOrder,
                    description: 'تم تسجيل دفعة يدوية على الطلب من لوحة الإدارة.',
                    newValues: [
                        'payment_method' => $method->value,
                        'payment_method_label' => $method->label(),
                        'recorded_amount' => $remainingAmount,
                        'reference_number' => $data['reference_number'] ?? null,
                        'terminal_id' => $terminal?->id,
                        'terminal_name' => $terminal?->name,
                        'payment_status' => $processedOrder->payment_status,
                        'paid_amount' => (float) $processedOrder->paid_amount,
                        'remaining_payable_amount' => $processedOrder->remainingPayableAmount(),
                        'drawer_session_number' => $processedOrder->drawerSession?->session_number,
                        'notes' => $data['notes'],
                    ],
                );
            });
    }

    public static function markReadyAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('markReady')
            ->label('تحديد كجاهز')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('تحديد الطلب كجاهز')
            ->modalDescription('سيتم نقل الطلب إلى حالة جاهز مع تسجيل وقت الجاهزية في النظام.')
            ->visible(fn (Order $record): bool => static::canMarkReady($record))
            ->action(function (Order $record): void {
                $oldStatus = $record->status;

                $updatedOrder = app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record): Order {
                    return app(OrderLifecycleService::class)->transitionFromAdmin(
                        order: $record,
                        newStatus: OrderStatus::Ready,
                        by: auth()->user(),
                    );
                });

                static::logStatusTransition(
                    order: $updatedOrder,
                    action: 'marked_ready',
                    oldStatus: $oldStatus,
                    newStatus: OrderStatus::Ready,
                    description: 'تم تحديد الطلب كجاهز من لوحة الإدارة.',
                );
            });
    }

    public static function markDeliveredAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('markDelivered')
            ->label('تحديد كتم التسليم')
            ->icon('heroicon-o-truck')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('تحديد الطلب كتم التسليم')
            ->modalDescription('سيتم نقل الطلب إلى حالة تم التسليم مع تسجيل وقت التسليم في النظام.')
            ->visible(fn (Order $record): bool => static::canMarkDelivered($record))
            ->action(function (Order $record): void {
                $oldStatus = $record->status;

                $updatedOrder = app(AdminActivityLogService::class)->withoutModelLogging(function () use ($record): Order {
                    if ($record->status === OrderStatus::Ready) {
                        return app(OrderLifecycleService::class)->markHandedOverFromAdmin($record, auth()->user());
                    }

                    return app(OrderLifecycleService::class)->transitionFromAdmin(
                        order: $record,
                        newStatus: OrderStatus::Delivered,
                        by: auth()->user(),
                    );
                });

                static::logStatusTransition(
                    order: $updatedOrder,
                    action: 'marked_delivered',
                    oldStatus: $oldStatus,
                    newStatus: OrderStatus::Delivered,
                    description: 'تم تحديد الطلب كتم التسليم من لوحة الإدارة.',
                );
            });
    }

    public static function canRecordManualPayment(Order $record): bool
    {
        return !$record->trashed()
            && $record->status !== OrderStatus::Cancelled
            && $record->status !== OrderStatus::Refunded
            && $record->payment_status !== PaymentStatus::Paid
            && (float) $record->remainingPayableAmount() > 0
            && $record->drawerSession?->isOpen()
            && auth()->user()?->hasPermission('orders.record_payment');
    }

    public static function canMarkReady(Order $record): bool
    {
        return !$record->trashed()
            && $record->status->canTransitionTo(OrderStatus::Ready)
            && auth()->user()?->hasPermission('mark_order_ready');
    }

    public static function canMarkDelivered(Order $record): bool
    {
        if ($record->trashed()) {
            return false;
        }

        if ($record->status === OrderStatus::Ready) {
            return auth()->user()?->hasPermission('handover_counter_orders') && $record->isPaid();
        }

        return $record->status->canTransitionTo(OrderStatus::Delivered)
            && auth()->user()?->hasPermission('handover_counter_orders');
    }

    public static function logStatusTransition(
        Order $order,
        string $action,
        OrderStatus $oldStatus,
        OrderStatus $newStatus,
        string $description,
    ): void {
        app(AdminActivityLogService::class)->logAction(
            action: $action,
            subject: $order,
            description: $description,
            oldValues: [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            newValues: [
                'status' => $newStatus->value,
                'status_label' => $newStatus->label(),
                'ready_at' => $order->ready_at,
                'delivered_at' => $order->delivered_at,
            ],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view'  => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
