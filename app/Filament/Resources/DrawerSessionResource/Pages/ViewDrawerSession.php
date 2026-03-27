<?php

namespace App\Filament\Resources\DrawerSessionResource\Pages;

use App\Enums\CashMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\FormatsAdminDetailValues;
use App\Filament\Resources\DrawerSessionResource;
use App\Filament\Resources\OrderResource;
use App\Models\CashierDrawerSession;
use App\Models\Order;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Filament\Resources\Pages\ViewRecord;

class ViewDrawerSession extends ViewRecord
{
    use FormatsAdminDetailValues;

    protected static string $resource = DrawerSessionResource::class;
    protected string $view = 'filament.resources.drawer-session-resource.pages.view-drawer-session';

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->record->load([
            'cashier:id,name',
            'shift:id,shift_number',
            'posDevice:id,name',
            'opener:id,name',
            'closer:id,name',
            'cashMovements.performer:id,name',
            'orders.cashier:id,name',
            'orders.items',
            'orders.payments.terminal:id,name,bank_name,code',
            'expenses.category:id,name',
            'expenses.approver:id,name',
        ]);
    }

    public function getTitle(): string | Htmlable
    {
        return 'جلسة الدرج ' . $this->record->session_number;
    }

    public function getSubtitle(): string
    {
        return 'عرض تشغيلي ومالي متكامل للجلسة، مع الطلبات والحركات والمصروفات في تنسيق أوضح وأسهل مراجعة.';
    }

    public function getPrimaryStats(): array
    {
        $record = $this->getRecord();
        $orders = $record->orders;

        return [
            [
                'title' => 'إجمالي المبيعات',
                'value' => $this->formatMoney($orders->sum('total')),
                'hint' => $this->formatNumber($orders->count()) . ' طلب',
                'tone' => 'primary',
            ],
            [
                'title' => 'طلبات مدفوعة',
                'value' => $this->formatNumber($orders->where('payment_status', PaymentStatus::Paid)->count()),
                'hint' => 'من أصل ' . $this->formatNumber($orders->count()) . ' طلب',
                'tone' => 'success',
            ],
            [
                'title' => 'الرصيد المتوقع',
                'value' => $this->formatMoney($record->expected_balance),
                'hint' => 'قبل الجرد الفعلي',
                'tone' => 'warning',
            ],
            [
                'title' => 'فرق الجرد',
                'value' => $this->formatMoney($record->cash_difference),
                'hint' => $this->getDifferenceHint((float) $record->cash_difference),
                'tone' => $this->differenceTone($record->cash_difference),
            ],
        ];
    }

    public function getSecondaryStats(): array
    {
        $orders = $this->getRecord()->orders;
        $payments = $orders->flatMap->payments;
        $items = $orders->flatMap->items;
        $orderCount = max(1, $orders->count());

        return [
            [
                'title' => 'مبيعات نقدية',
                'value' => $this->formatMoney($payments->where('payment_method', PaymentMethod::Cash)->sum('amount')),
                'hint' => 'تدخل ضمن رصيد الدرج',
                'tone' => 'success',
            ],
            [
                'title' => 'مبيعات بطاقة',
                'value' => $this->formatMoney($payments->where('payment_method', PaymentMethod::Card)->sum('amount')),
                'hint' => 'محصلة غير نقدية',
                'tone' => 'info',
            ],
            [
                'title' => 'رسوم البطاقات',
                'value' => $this->formatMoney($payments->where('payment_method', PaymentMethod::Card)->sum('fee_amount')),
                'hint' => 'تُسجل منفصلة عن قيمة الطلب',
                'tone' => 'danger',
            ],
            [
                'title' => 'صافي تسوية البطاقات',
                'value' => $this->formatMoney($payments->where('payment_method', PaymentMethod::Card)->sum('net_settlement_amount')),
                'hint' => 'بعد خصم الرسوم',
                'tone' => 'primary',
            ],
            [
                'title' => 'متوسط قيمة الطلب',
                'value' => $this->formatMoney($orders->sum('total') / $orderCount),
                'hint' => 'لكل طلب داخل الجلسة',
                'tone' => 'warning',
            ],
            [
                'title' => 'عدد الأصناف المباعة',
                'value' => $this->formatNumber($items->sum('quantity')),
                'hint' => $this->formatNumber($items->count()) . ' سطر طلب',
                'tone' => 'neutral',
            ],
        ];
    }

    public function getOperationalSnapshot(): array
    {
        $record = $this->getRecord();

        return [
            ['label' => 'الكاشير', 'value' => $record->cashier?->name ?? '—'],
            ['label' => 'الوردية', 'value' => $record->shift?->shift_number ?? '—'],
            ['label' => 'الجهاز', 'value' => $record->posDevice?->name ?? '—'],
            ['label' => 'الحالة', 'value' => $record->status->label(), 'tone' => $this->drawerStatusTone($record->status)],
            ['label' => 'فتح بواسطة', 'value' => $record->opener?->name ?? '—'],
            ['label' => 'أغلق بواسطة', 'value' => $record->closer?->name ?? '—'],
            ['label' => 'البداية', 'value' => $this->formatDateTime($record->started_at)],
            ['label' => 'النهاية', 'value' => $this->formatDateTime($record->ended_at)],
            ['label' => 'مدة الجلسة', 'value' => $record->started_at?->diffForHumans($record->ended_at ?? now(), true) ?? '—'],
            ['label' => 'ملاحظات', 'value' => filled($record->notes) ? $record->notes : 'لا توجد ملاحظات مسجلة.'],
        ];
    }

    public function getFinancialSnapshot(): array
    {
        $record = $this->getRecord();
        $orders = $record->orders;
        $payments = $orders->flatMap->payments;

        return [
            ['label' => 'رصيد الفتح', 'value' => $this->formatMoney($record->opening_balance)],
            ['label' => 'إجمالي مبيعات الطلبات', 'value' => $this->formatMoney($orders->sum('total'))],
            ['label' => 'إجمالي المدفوع', 'value' => $this->formatMoney($orders->sum('paid_amount'))],
            ['label' => 'إيداعات نقدية', 'value' => $this->formatMoney($record->cashMovements->where('type', CashMovementType::CashIn)->sum('amount'))],
            ['label' => 'سحوبات نقدية', 'value' => $this->formatMoney($record->cashMovements->where('type', CashMovementType::CashOut)->sum('amount'))],
            ['label' => 'استرجاعات', 'value' => $this->formatMoney($record->cashMovements->where('type', CashMovementType::Refund)->sum('amount'))],
            ['label' => 'مصروفات الجلسة', 'value' => $this->formatMoney($record->expenses->sum('amount'))],
            ['label' => 'رسوم البطاقات', 'value' => $this->formatMoney($payments->where('payment_method', PaymentMethod::Card)->sum('fee_amount'))],
            ['label' => 'الرصيد المتوقع', 'value' => $this->formatMoney($record->expected_balance)],
            ['label' => 'الرصيد الفعلي', 'value' => $this->formatMoney($record->closing_balance)],
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
        $payments = $this->getRecord()->orders->flatMap->payments;

        return collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $method): array => [
                'label' => $method->label(),
                'value' => $this->formatMoney($payments->where('payment_method', $method)->sum('amount')),
                'count' => $payments->where('payment_method', $method)->count(),
                'tone' => $this->paymentMethodTone($method),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values()
            ->all();
    }

    public function getTopSellingItems(): array
    {
        return $this->getRecord()->orders
            ->flatMap->items
            ->groupBy(fn ($item) => $item->item_name . ($item->variant_name ? ' - ' . $item->variant_name : ''))
            ->map(fn (Collection $items, string $name): array => [
                'name' => $name,
                'quantity' => $items->sum('quantity'),
                'sales' => $items->sum('total'),
            ])
            ->sortByDesc('quantity')
            ->take(5)
            ->values()
            ->all();
    }

    public function getOrdersTableData(): Collection
    {
        return $this->getRecord()->orders->sortByDesc('created_at')->values();
    }

    public function getCashMovementsTableData(): Collection
    {
        return $this->getRecord()->cashMovements->sortByDesc('created_at')->values();
    }

    public function getExpensesTableData(): Collection
    {
        return $this->getRecord()->expenses->sortByDesc('expense_date')->values();
    }

    public function getOrderViewUrl(Order $order): string
    {
        return OrderResource::getUrl('view', ['record' => $order]);
    }

    protected function getDifferenceHint(float $difference): string
    {
        return match (true) {
            $difference > 0 => 'يوجد فائض مسجل',
            $difference < 0 => 'يوجد عجز مسجل',
            default => 'الجرد مطابق',
        };
    }
}
