<?php

namespace App\Filament\Resources;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
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
                        app(OrderLifecycleService::class)->cancel($record, auth()->user(), $data['reason']);
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
                Infolists\Components\TextEntry::make('customer_name')->label('العميل')->placeholder('—'),
                Infolists\Components\TextEntry::make('customer_phone')->label('هاتف العميل')->placeholder('—'),
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
            ])->columns(3),
            \Filament\Schemas\Components\Section::make('الأصناف المطلوبة')->schema([
                Infolists\Components\RepeatableEntry::make('items')->label('')->schema([
                    Infolists\Components\TextEntry::make('item_name')->label('الصنف'),
                    Infolists\Components\TextEntry::make('quantity')->label('الكمية')->numeric(),
                    Infolists\Components\TextEntry::make('unit_price')->label('سعر الوحدة')->money('EGP'),
                    Infolists\Components\TextEntry::make('total')->label('الإجمالي')->money('EGP'),
                    Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—'),
                ])->columns(5),
            ]),
            \Filament\Schemas\Components\Section::make('المدفوعات')->schema([
                Infolists\Components\RepeatableEntry::make('payments')->label('')->schema([
                    Infolists\Components\TextEntry::make('payment_method')->label('طريقة الدفع'),
                    Infolists\Components\TextEntry::make('amount')->label('المبلغ')->money('EGP'),
                    Infolists\Components\TextEntry::make('reference_number')->label('مرجع')->placeholder('—'),
                    Infolists\Components\TextEntry::make('created_at')->label('التوقيت')->dateTime(),
                ])->columns(4),
            ]),
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
