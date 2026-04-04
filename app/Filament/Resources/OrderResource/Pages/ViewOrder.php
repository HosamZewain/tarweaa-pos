<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\HasPrintPageAction;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\AdminActivityLogService;
use App\Services\AdminExcelExportService;
use App\Services\OrderDeletionService;
use App\Services\OrderLifecycleService;
use App\Support\BusinessTime;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    use HasPrintPageAction;

    protected static string $resource = OrderResource::class;

    protected function resolveRecord(int | string $key): Order
    {
        /** @var Order $record */
        $record = parent::resolveRecord($key);

        return $record->load([
            'shift',
            'drawerSession',
            'posDevice',
            'cashier',
            'customer',
            'refunder',
            'canceller',
            'items.modifiers',
            'payments',
            'orderDiscountLogs.appliedBy',
            'orderDiscountLogs.requestedBy',
            'latestOrderDiscountLog.appliedBy',
            'latestOrderDiscountLog.requestedBy',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->makePrintPageAction(),
            Actions\Action::make('exportExcel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(AdminExcelExportService::class)->downloadFromData(
                    'order-' . $this->record->order_number . '.xlsx',
                    $this->getExcelExportPayload(),
                )),
            OrderResource::recordPaymentAction(),
            Actions\Action::make('cancel')
                ->label('إلغاء')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('إلغاء الطلب')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->label('سبب الإلغاء')->required(),
                ])
                ->visible(fn () => $this->record->isCancellable() && !$this->record->hasNonCashPayments() && auth()->user()?->hasPermission('orders.cancel'))
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->hasPermission('orders.cancel'), 403);

                    app(AdminActivityLogService::class)->withoutModelLogging(function () use ($data): void {
                        app(OrderLifecycleService::class)->cancel($this->record, auth()->user(), $data['reason']);
                    });

                    $this->record->refresh();

                    app(AdminActivityLogService::class)->logAction(
                        action: 'cancelled',
                        subject: $this->record,
                        description: 'تم إلغاء طلب من شاشة عرض الطلب.',
                        newValues: [
                            'status' => $this->record->status,
                            'cancellation_reason' => $data['reason'],
                        ],
                    );
                }),
            OrderResource::recordPaymentAction(),
            Actions\Action::make('safe_delete')
                ->label('حذف آمن')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('حذف الطلب مع عكس العمليات')
                ->modalDescription('سيتم عكس المخزون والمدفوعات النقدية والتسويات المرتبطة ثم إخفاء الطلب من النظام.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->label('سبب الحذف')->required(),
                ])
                ->visible(fn () => !$this->record->trashed() && !$this->record->hasNonCashPayments() && !$this->record->isCancellable() && auth()->user()?->hasPermission('orders.delete'))
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->hasPermission('orders.delete'), 403);

                    app(AdminActivityLogService::class)->withoutModelLogging(function () use ($data): void {
                        app(OrderDeletionService::class)->deleteWithReversal($this->record, auth()->user(), $data['reason']);
                    });

                    app(AdminActivityLogService::class)->logAction(
                        action: 'deleted',
                        subject: $this->record,
                        description: 'تم حذف الطلب مع عكس العمليات من شاشة عرض الطلب.',
                        newValues: [
                            'status' => \App\Enums\OrderStatus::Cancelled->value,
                            'deletion_reason' => $data['reason'],
                            'deleted_at' => now(),
                        ],
                    );

                    $this->redirect(OrderResource::getUrl());
                }),
        ];
    }

    private function getExcelExportPayload(): array
    {
        $timezone = BusinessTime::timezone();
        $order = $this->record->loadMissing([
            'shift',
            'drawerSession',
            'posDevice',
            'cashier',
            'customer',
            'refunder',
            'canceller',
            'items.modifiers',
            'payments.terminal',
            'orderDiscountLogs.appliedBy',
            'orderDiscountLogs.requestedBy',
            'settlement',
        ]);

        return [
            'summary' => [
                'رقم الطلب' => $order->order_number,
                'النوع' => $order->type->label(),
                'الحالة' => $order->status->label(),
                'المصدر' => $order->source->label(),
                'الكاشير' => $order->cashier?->name,
                'الوردية' => $order->shift?->shift_number,
                'جلسة الدرج' => $order->drawerSession?->session_number,
                'الجهاز' => $order->posDevice?->name,
                'العميل' => $order->customer_name,
                'هاتف العميل' => $order->customer_phone,
                'الإجمالي' => (float) $order->total,
                'المدفوع' => (float) $order->reportablePaidAmount(),
                'الباقي' => (float) $order->change_amount,
                'الخصم' => (float) $order->discount_amount,
                'الضريبة' => (float) $order->tax_amount,
                'رسوم التوصيل' => (float) $order->delivery_fee,
                'حالة الدفع' => $order->payment_status->label(),
                'وقت الإنشاء' => $order->created_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                'وقت التسليم' => $order->delivered_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                'سبب الإلغاء' => $order->cancellation_reason,
                'سبب الاسترجاع' => $order->refund_reason,
            ],
            'items' => $order->items->map(function ($item): array {
                return [
                    'الصنف' => $item->item_name,
                    'النوع الفرعي' => $item->variant_name,
                    'الكمية' => (float) $item->quantity,
                    'سعر الوحدة' => (float) $item->unit_price,
                    'خصم السطر' => (float) $item->discount_amount,
                    'الإجمالي' => (float) $item->total,
                    'الحالة' => $item->status?->label(),
                    'الإضافات' => collect($item->modifiers ?? [])
                        ->map(fn ($modifier) => $modifier->modifier_name . ((int) $modifier->quantity > 1 ? ' × ' . $modifier->quantity : ''))
                        ->implode('، '),
                    'ملاحظات' => $item->notes,
                ];
            })->all(),
            'payments' => $order->payments->map(function ($payment) use ($timezone): array {
                $paymentMethod = $payment->payment_method instanceof PaymentMethod
                    ? $payment->payment_method->label()
                    : (PaymentMethod::tryFrom((string) $payment->payment_method)?->label() ?? (string) $payment->payment_method);

                return [
                    'طريقة الدفع' => $paymentMethod,
                    'المبلغ' => (float) $payment->amount,
                    'مرجع العملية' => $payment->reference_number,
                    'الجهاز' => $payment->terminal?->name,
                    'ملاحظات' => $payment->notes,
                    'التوقيت' => $payment->created_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                ];
            })->all(),
            'discount_logs' => $order->orderDiscountLogs->map(function ($log) use ($timezone): array {
                return [
                    'الإجراء' => $log->action,
                    'طلب بواسطة' => $log->requestedBy?->name,
                    'اعتمد بواسطة' => $log->appliedBy?->name,
                    'النوع' => $log->discount_type,
                    'القيمة' => $log->discount_value,
                    'المبلغ الفعلي' => $log->discount_amount,
                    'السبب' => $log->reason,
                    'التوقيت' => $log->created_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                ];
            })->all(),
        ];
    }
}
