<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Enums\CashMovementType;
use App\Enums\DrawerSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\FormatsAdminDetailValues;
use App\Filament\Resources\DrawerSessionResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ShiftResource;
use App\Models\CashierDrawerSession;
use App\Models\Order;
use App\Models\Shift;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Filament\Resources\Pages\ViewRecord;

class ViewShift extends ViewRecord
{
    use FormatsAdminDetailValues;

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

    public function getPrimaryStats(): array
    {
        $record = $this->getRecord();
        $orders = $record->orders;
        $payments = $orders->flatMap->payments;

        return [
            [
                'title' => 'إجمالي المبيعات',
                'value' => $this->formatMoney($orders->sum('total')),
                'hint' => $this->formatNumber($orders->count()) . ' طلب خلال الوردية',
                'tone' => 'primary',
            ],
            [
                'title' => 'مبيعات نقدية',
                'value' => $this->formatMoney($payments->where('payment_method', PaymentMethod::Cash)->sum('amount')),
                'hint' => 'محصلة عبر الأدراج',
                'tone' => 'success',
            ],
            [
                'title' => 'مبيعات غير نقدية',
                'value' => $this->formatMoney($payments->reject(
                    fn ($payment) => $payment->payment_method === PaymentMethod::Cash
                )->sum('amount')),
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
        $orders = $record->orders;
        $payments = $orders->flatMap->payments;
        $items = $orders->flatMap->items;
        $orderCount = max(1, $orders->count());

        return [
            [
                'title' => 'طلبات مدفوعة',
                'value' => $this->formatNumber($orders->where('payment_status', PaymentStatus::Paid)->count()),
                'hint' => 'من أصل ' . $this->formatNumber($orders->count()) . ' طلب',
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
                'value' => $this->formatMoney($payments->where('payment_method', PaymentMethod::Card)->sum('fee_amount')),
                'hint' => 'رسوم منفصلة عن المبيعات',
                'tone' => 'danger',
            ],
            [
                'title' => 'صافي تسوية البطاقات',
                'value' => $this->formatMoney($payments->where('payment_method', PaymentMethod::Card)->sum('net_settlement_amount')),
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
            ['label' => 'طلبات ملغاة', 'value' => $this->formatNumber($record->orders->where('status', OrderStatus::Cancelled)->count())],
            ['label' => 'ملاحظات', 'value' => filled($record->notes) ? $record->notes : 'لا توجد ملاحظات مسجلة.'],
        ];
    }

    public function getFinancialSnapshot(): array
    {
        $record = $this->getRecord();
        $orders = $record->orders;

        return [
            ['label' => 'النقد المتوقع', 'value' => $this->formatMoney($record->expected_cash)],
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
}
