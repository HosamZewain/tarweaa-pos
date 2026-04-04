<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Enums\CashMovementType;
use App\Enums\DrawerSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\FormatsAdminDetailValues;
use App\Filament\Concerns\HasPrintPageAction;
use App\Filament\Resources\DrawerSessionResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ShiftResource;
use App\Models\CashierDrawerSession;
use App\Models\Order;
use App\Models\Shift;
use App\Services\AdminExcelExportService;
use App\Support\BusinessTime;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Filament\Resources\Pages\ViewRecord;

class ViewShift extends ViewRecord
{
    use FormatsAdminDetailValues;
    use HasPrintPageAction;

    protected static string $resource = ShiftResource::class;
    protected string $view = 'filament.resources.shift-resource.pages.view-shift';

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->record->load([
            'opener:id,name',
            'closer:id,name',
            'drawerSessions.cashier:id,name',
            'drawerSessions.posDevice:id,name',
            'drawerSessions.cashMovements',
            'drawerSessions.orders',
            'orders.cashier:id,name',
            'orders.drawerSession:id,session_number',
            'orders.items',
            'orders.payments.terminal:id,name,bank_name,code',
            'expenses.category:id,name',
            'expenses.drawerSession:id,session_number',
            'expenses.approver:id,name',
        ]);
    }

    public function getTitle(): string | Htmlable
    {
        return 'تفاصيل الوردية ' . $this->record->shift_number;
    }

    public function getSubtitle(): string
    {
        return 'عرض موحد للوردية يشمل الأداء المالي، الأدراج، الطلبات، المصروفات، والتوزيعات التشغيلية في تنسيق أكثر وضوحًا.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->makePrintPageAction(),
            Action::make('exportExcel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(AdminExcelExportService::class)->downloadFromData(
                    'shift-' . $this->record->shift_number . '.xlsx',
                    $this->getExcelExportPayload(),
                )),
        ];
    }

    public function getPrimaryStats(): array
    {
        $record = $this->getRecord();
        $orders = $record->revenueOrdersCollection();
        $this->loadOrderPaymentContext($orders);
        $cashSales = round($orders->sum(fn (Order $order) => $order->reportableCashPaidAmount()), 2);
        $nonCashSales = round($orders->sum(fn (Order $order) => $order->reportableNonCashPaidAmount()), 2);
        $allOrdersCount = $record->reportableOrdersCollection()->count();

        return [
            [
                'title' => 'إجمالي المبيعات',
                'value' => $this->formatMoney($orders->sum('total')),
                'hint' => $this->formatNumber($orders->count()) . ' طلب مدفوع من أصل ' . $this->formatNumber($allOrdersCount),
                'tone' => 'primary',
            ],
            [
                'title' => 'مبيعات نقدية',
                'value' => $this->formatMoney($cashSales),
                'hint' => 'محصلة عبر الأدراج',
                'tone' => 'success',
            ],
            [
                'title' => 'مبيعات غير نقدية',
                'value' => $this->formatMoney($nonCashSales),
                'hint' => 'بطاقة، طلبات، إنستاباي وغيرها',
                'tone' => 'info',
            ],
            [
                'title' => 'فرق الوردية',
                'value' => $this->formatMoney($record->cash_difference),
                'hint' => $this->getDifferenceHint((float) $record->cash_difference),
                'tone' => $this->differenceTone($record->cash_difference),
            ],
        ];
    }

    public function getSecondaryStats(): array
    {
        $record = $this->getRecord();
        $orders = $record->revenueOrdersCollection();
        $this->loadOrderPaymentContext($orders);
        $allOrdersCount = $record->reportableOrdersCollection()->count();
        $cardPayments = $orders->flatMap->payments
            ->where('payment_method', PaymentMethod::Card);
        $items = $orders->flatMap->items;
        $orderCount = max(1, $orders->count());

        return [
            [
                'title' => 'طلبات مدفوعة',
                'value' => $this->formatNumber($orders->count()),
                'hint' => 'من أصل ' . $this->formatNumber($allOrdersCount) . ' طلب',
                'tone' => 'success',
            ],
            [
                'title' => 'متوسط قيمة الطلب',
                'value' => $this->formatMoney($orders->sum('total') / $orderCount),
                'hint' => 'لكل طلب داخل الوردية',
                'tone' => 'warning',
            ],
            [
                'title' => 'رسوم البطاقات',
                'value' => $this->formatMoney($cardPayments->sum('fee_amount')),
                'hint' => 'رسوم منفصلة عن المبيعات',
                'tone' => 'danger',
            ],
            [
                'title' => 'صافي تسوية البطاقات',
                'value' => $this->formatMoney($cardPayments->sum('net_settlement_amount')),
                'hint' => 'بعد خصم الرسوم',
                'tone' => 'primary',
            ],
            [
                'title' => 'عدد الأدراج',
                'value' => $this->formatNumber($record->drawerSessions->count()),
                'hint' => $this->formatNumber($record->drawerSessions->where('status', DrawerSessionStatus::Open)->count()) . ' مفتوح حاليًا',
                'tone' => 'neutral',
            ],
            [
                'title' => 'عدد الأصناف المباعة',
                'value' => $this->formatNumber($items->sum('quantity')),
                'hint' => $this->formatNumber($items->count()) . ' سطر طلب',
                'tone' => 'info',
            ],
        ];
    }

    public function getOperationalSnapshot(): array
    {
        $record = $this->getRecord();

        return [
            ['label' => 'الحالة', 'value' => $record->status->label(), 'tone' => $this->shiftStatusTone($record->status)],
            ['label' => 'فتح بواسطة', 'value' => $record->opener?->name ?? '—'],
            ['label' => 'أغلق بواسطة', 'value' => $record->closer?->name ?? '—'],
            ['label' => 'البداية', 'value' => $this->formatDateTime($record->started_at)],
            ['label' => 'النهاية', 'value' => $this->formatDateTime($record->ended_at)],
            ['label' => 'مدة الوردية', 'value' => $record->durationLabel()],
            ['label' => 'عدد الكاشير', 'value' => $this->formatNumber($record->drawerSessions->pluck('cashier_id')->unique()->count())],
            ['label' => 'أدراج مفتوحة', 'value' => $this->formatNumber($record->drawerSessions->where('status', DrawerSessionStatus::Open)->count())],
            ['label' => 'طلبات ملغاة', 'value' => $this->formatNumber($record->cancelledOrdersCollection()->count())],
            ['label' => 'ملاحظات', 'value' => filled($record->notes) ? $record->notes : 'لا توجد ملاحظات مسجلة.'],
        ];
    }

    public function getFinancialSnapshot(): array
    {
        $record = $this->getRecord();
        $orders = $record->revenueOrdersCollection();

        return [
            ['label' => 'النقد المتوقع', 'value' => $this->formatMoney($record->calculateExpectedCashFromDrawers())],
            ['label' => 'النقد الفعلي', 'value' => $this->formatMoney($record->actual_cash)],
            ['label' => 'إجمالي الخصومات', 'value' => $this->formatMoney($orders->sum('discount_amount'))],
            ['label' => 'إجمالي الضريبة', 'value' => $this->formatMoney($orders->sum('tax_amount'))],
            ['label' => 'رسوم التوصيل', 'value' => $this->formatMoney($orders->sum('delivery_fee'))],
            ['label' => 'إجمالي الاسترجاعات', 'value' => $this->formatMoney($orders->sum('refund_amount'))],
            ['label' => 'المصروفات المعتمدة', 'value' => $this->formatMoney($record->expenses->sum('amount'))],
            ['label' => 'صافي النقد بعد المصروفات', 'value' => $this->formatMoney(((float) $record->actual_cash) - $record->expenses->sum('amount'))],
        ];
    }

    public function getOrderStatusStats(): array
    {
        $orders = $this->getRecord()->orders;

        return collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status): array => [
                'label' => $status->label(),
                'value' => $orders->where('status', $status)->count(),
                'tone' => $this->orderStatusTone($status),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->values()
            ->all();
    }

    public function getPaymentMethodStats(): array
    {
        $orders = $this->getRecord()->revenueOrdersCollection();
        $this->loadOrderPaymentContext($orders);

        return collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $method): array => [
                'label' => $method->label(),
                'value' => $this->formatMoney(round($orders->sum(fn (Order $order) => $order->reportablePaidAmountForMethod($method)), 2)),
                'count' => $orders->filter(fn (Order $order) => $order->reportablePaidAmountForMethod($method) > 0)->count(),
                'tone' => $this->paymentMethodTone($method),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values()
            ->all();
    }

    public function getTopSellingItems(): array
    {
        return $this->getRecord()->revenueOrdersCollection()
            ->flatMap->items
            ->groupBy(fn ($item) => $item->item_name . ($item->variant_name ? ' - ' . $item->variant_name : ''))
            ->map(fn (Collection $items, string $name): array => [
                'name' => $name,
                'quantity' => $items->sum('quantity'),
                'sales' => $items->sum('total'),
            ])
            ->sortByDesc('quantity')
            ->take(7)
            ->values()
            ->all();
    }

    public function getDrawerSessionsTableData(): Collection
    {
        return $this->getRecord()->drawerSessions->sortByDesc('started_at')->values();
    }

    public function getOrdersTableData(): Collection
    {
        return $this->getRecord()->orders->sortByDesc('created_at')->values();
    }

    public function getExpensesTableData(): Collection
    {
        return $this->getRecord()->expenses->sortByDesc('expense_date')->values();
    }

    public function getDrawerSessionViewUrl(CashierDrawerSession $session): string
    {
        return DrawerSessionResource::getUrl('view', ['record' => $session]);
    }

    public function getOrderViewUrl(Order $order): string
    {
        return OrderResource::getUrl('view', ['record' => $order]);
    }

    protected function getDifferenceHint(float $difference): string
    {
        return match (true) {
            $difference > 0 => 'فائض مسجل على الوردية',
            $difference < 0 => 'عجز مسجل على الوردية',
            default => 'الجرد مطابق',
        };
    }

    private function loadOrderPaymentContext(Collection $orders): void
    {
        if ($orders->isNotEmpty()) {
            $orders->loadMissing('payments', 'settlement');
        }
    }

    private function getExcelExportPayload(): array
    {
        $timezone = BusinessTime::timezone();
        $shift = $this->record->loadMissing([
            'opener:id,name',
            'closer:id,name',
            'drawerSessions.cashier:id,name',
            'drawerSessions.posDevice:id,name',
            'drawerSessions.cashMovements',
            'orders.cashier:id,name',
            'orders.drawerSession:id,session_number',
            'orders.items',
            'orders.payments',
            'orders.settlement',
            'expenses.category:id,name',
            'expenses.approver:id,name',
        ]);

        return [
            'summary' => [
                'رقم الوردية' => $shift->shift_number,
                'الحالة' => $shift->status->label(),
                'فتح بواسطة' => $shift->opener?->name,
                'أغلق بواسطة' => $shift->closer?->name,
                'البداية' => $shift->started_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                'النهاية' => $shift->ended_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                'إجمالي المبيعات' => (float) $shift->revenueOrdersCollection()->sum('total'),
                'المبيعات النقدية' => round($shift->revenueOrdersCollection()->loadMissing('payments', 'settlement')->sum(fn (Order $order) => $order->reportableCashPaidAmount()), 2),
                'المبيعات غير النقدية' => round($shift->revenueOrdersCollection()->loadMissing('payments', 'settlement')->sum(fn (Order $order) => $order->reportableNonCashPaidAmount()), 2),
                'النقد المتوقع' => (float) $shift->calculateExpectedCashFromDrawers(),
                'النقد الفعلي' => $shift->actual_cash !== null ? (float) $shift->actual_cash : null,
                'فرق النقد' => $shift->cash_difference !== null ? (float) $shift->cash_difference : null,
                'عدد الطلبات المدفوعة' => $shift->revenueOrdersCollection()->count(),
                'عدد الطلبات الملغاة' => $shift->cancelledOrdersCollection()->count(),
            ],
            'drawer_sessions' => $shift->drawerSessions->map(function (CashierDrawerSession $session) use ($timezone): array {
                return [
                    'رقم الجلسة' => $session->session_number,
                    'الكاشير' => $session->cashier?->name,
                    'الجهاز' => $session->posDevice?->name,
                    'الحالة' => $session->status->label(),
                    'رصيد الفتح' => (float) $session->opening_balance,
                    'الرصيد المتوقع' => (float) $session->calculateExpectedBalance(),
                    'الرصيد الفعلي' => $session->closing_balance !== null ? (float) $session->closing_balance : null,
                    'فرق الجرد' => $session->cash_difference !== null ? (float) $session->cash_difference : null,
                    'البداية' => $session->started_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                    'النهاية' => $session->ended_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                ];
            })->all(),
            'orders' => $shift->orders->map(function (Order $order) use ($timezone): array {
                return [
                    'رقم الطلب' => $order->order_number,
                    'الكاشير' => $order->cashier?->name,
                    'جلسة الدرج' => $order->drawerSession?->session_number,
                    'الحالة' => $order->status->label(),
                    'حالة الدفع' => $order->payment_status->label(),
                    'الإجمالي' => (float) $order->total,
                    'المدفوع' => (float) $order->reportablePaidAmount(),
                    'وقت الإنشاء' => $order->created_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                ];
            })->all(),
            'expenses' => $shift->expenses->map(function ($expense) use ($timezone): array {
                return [
                    'رقم المصروف' => $expense->expense_number,
                    'الفئة' => $expense->category?->name,
                    'المبلغ' => (float) $expense->amount,
                    'طريقة الدفع' => $expense->payment_method,
                    'اعتمد بواسطة' => $expense->approver?->name,
                    'التاريخ' => $expense->expense_date?->timezone($timezone)->format('Y-m-d H:i:s'),
                    'الوصف' => $expense->description,
                ];
            })->all(),
        ];
    }
}
