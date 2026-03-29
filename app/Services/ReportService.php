<?php

namespace App\Services;

use App\Models\CashierDrawerSession;
use App\Models\DiscountLog;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Enums\PaymentMethod;
use App\Support\BusinessTime;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getDiscountAudit(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $scope = 'all',
        ?int $appliedBy = null,
        ?string $search = null,
    ): array {
        $logs = BusinessTime::applyUtcDateRange(
            DiscountLog::query()
                ->with([
                    'order.customer:id,name,phone',
                    'order.cashier:id,name',
                    'appliedBy:id,name',
                    'orderItem:id,order_id,item_name',
                ])
                ->when($scope && $scope !== 'all', fn ($query) => $query->where('scope', $scope))
                ->when($appliedBy, fn ($query, $userId) => $query->where('applied_by', $userId))
                ->when($search, function ($query, $term) {
                    $query->where(function ($inner) use ($term) {
                        $inner->whereHas('order', function ($orderQuery) use ($term) {
                            $orderQuery->where('order_number', 'like', "%{$term}%")
                                ->orWhere('customer_name', 'like', "%{$term}%")
                                ->orWhere('customer_phone', 'like', "%{$term}%");
                        });
                    });
                })
                ->orderByDesc('created_at'),
            $dateFrom,
            $dateTo,
        )->get();

        $summary = [
            'total_events' => $logs->count(),
            'discounted_orders' => $logs->pluck('order_id')->filter()->unique()->count(),
            'discounted_clients' => $logs
                ->map(fn (DiscountLog $log) => $log->order?->customer_phone ?: $log->order?->customer_name)
                ->filter()
                ->unique()
                ->count(),
            'total_discount_amount' => round($logs->sum(fn (DiscountLog $log) => (float) $log->discount_amount), 2),
        ];

        $byActor = $logs
            ->groupBy(fn (DiscountLog $log) => $log->appliedBy?->name ?? 'غير محدد')
            ->map(fn ($group, $actor) => [
                'actor' => $actor,
                'events' => $group->count(),
                'total_discount_amount' => round($group->sum(fn (DiscountLog $log) => (float) $log->discount_amount), 2),
                'orders' => $group->pluck('order_id')->unique()->count(),
            ])
            ->sortByDesc('total_discount_amount')
            ->values();

        $byCustomer = $logs
            ->groupBy(fn (DiscountLog $log) => $log->order?->customer_name ?: 'بدون عميل')
            ->map(fn ($group, $customerName) => [
                'customer_name' => $customerName,
                'customer_phone' => $group->first()?->order?->customer_phone,
                'events' => $group->count(),
                'orders' => $group->pluck('order_id')->unique()->count(),
                'total_discount_amount' => round($group->sum(fn (DiscountLog $log) => (float) $log->discount_amount), 2),
            ])
            ->sortByDesc('total_discount_amount')
            ->values();

        return [
            'logs' => $logs,
            'summary' => $summary,
            'byActor' => $byActor,
            'byCustomer' => $byCustomer,
        ];
    }

    public function getDailySales(string $dateFrom, string $dateTo): array
    {
        $orders = BusinessTime::applyUtcDateRange(
            Order::query(),
            $dateFrom,
            $dateTo,
        )
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $daily = BusinessTime::groupByLocalDate($orders)
            ->map(function (Collection $group, string $date) {
                return (object) [
                    'date' => $date,
                    'total_orders' => $group->count(),
                    'subtotal' => round($group->sum('subtotal'), 2),
                    'total_discounts' => round($group->sum('discount_amount'), 2),
                    'total_tax' => round($group->sum('tax_amount'), 2),
                    'total_delivery_fees' => round($group->sum('delivery_fee'), 2),
                    'gross_revenue' => round($group->sum('total'), 2),
                    'total_refunds' => round($group->sum('refund_amount'), 2),
                    'net_revenue' => round($group->sum('total') - $group->sum('refund_amount'), 2),
                ];
            })
            ->sortBy('date')
            ->values();

        $totals = [
            'total_orders'       => $daily->sum('total_orders'),
            'gross_revenue'      => round($daily->sum('gross_revenue'), 2),
            'total_discounts'    => round($daily->sum('total_discounts'), 2),
            'total_tax'          => round($daily->sum('total_tax'), 2),
            'total_delivery_fees' => round($daily->sum('total_delivery_fees'), 2),
            'total_refunds'      => round($daily->sum('total_refunds'), 2),
            'net_revenue'        => round($daily->sum('net_revenue'), 2),
        ];

        return [
            'daily'  => $daily,
            'totals' => $totals,
        ];
    }

    public function getSalesByItem(?string $dateFrom = null, ?string $dateTo = null, int $limit = 50): Collection
    {
        return OrderItem::whereHas('order', function ($q) use ($dateFrom, $dateTo) {
            BusinessTime::applyUtcDateRange(
                $q->whereNotIn('status', ['cancelled']),
                $dateFrom,
                $dateTo,
            );
        })
            ->selectRaw('menu_item_id, item_name, SUM(quantity) as total_quantity, SUM(total) as net_revenue')
            ->groupBy('menu_item_id', 'item_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    public function getSalesByCategory(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return OrderItem::join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->whereHas('order', function ($q) use ($dateFrom, $dateTo) {
                BusinessTime::applyUtcDateRange(
                    $q->whereNotIn('status', ['cancelled']),
                    $dateFrom,
                    $dateTo,
                );
            })
            ->selectRaw('menu_categories.id as category_id, menu_categories.name as category_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.total) as net_revenue')
            ->groupBy('menu_categories.id', 'menu_categories.name')
            ->orderByDesc('net_revenue')
            ->get();
    }

    public function getSalesByPaymentMethod(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return OrderPayment::whereHas('order', function ($q) use ($dateFrom, $dateTo) {
            BusinessTime::applyUtcDateRange(
                $q->whereNotIn('status', ['cancelled']),
                $dateFrom,
                $dateTo,
            );
        })
            ->selectRaw('payment_method, COUNT(*) as transaction_count, SUM(amount) as total_amount')
            ->groupBy('payment_method')
            ->get();
    }

    public function getPlatformTransfersReport(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?array $methods = null,
    ): array {
        $selectedMethods = collect($methods)
            ->filter()
            ->values();

        if ($selectedMethods->isEmpty()) {
            $selectedMethods = collect([
                PaymentMethod::TalabatPay->value,
                PaymentMethod::JahezPay->value,
                PaymentMethod::Online->value,
                PaymentMethod::InstaPay->value,
            ]);
        }

        $payments = OrderPayment::query()
            ->with([
                'order:id,order_number,source,cashier_id,total,external_order_number,external_order_id,created_at',
                'order.cashier:id,name',
            ])
            ->whereIn('payment_method', $selectedMethods->all())
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', ['cancelled']))
            ->latest('created_at')
            ;

        $payments = BusinessTime::applyUtcDateRange($payments, $dateFrom, $dateTo)->get();

        $entries = $payments->map(function (OrderPayment $payment): array {
            $order = $payment->order;
            $method = $payment->payment_method;

            return [
                'payment_id' => $payment->id,
                'date' => BusinessTime::localDateString($payment->created_at),
                'date_time' => BusinessTime::asLocal($payment->created_at)->format('Y-m-d h:i A'),
                'order_number' => $order?->order_number,
                'order_url' => $order ? "/admin/orders/{$order->id}" : null,
                'order_source' => $order?->source?->label() ?? '—',
                'external_order_number' => $order?->external_order_number ?: $order?->external_order_id,
                'payment_method' => $method?->value ?? (string) $payment->getRawOriginal('payment_method'),
                'payment_method_label' => $method?->label() ?? (string) $payment->getRawOriginal('payment_method'),
                'amount' => round((float) $payment->amount, 2),
                'reference_number' => $payment->reference_number,
                'cashier_name' => $order?->cashier?->name ?? '—',
                'order_total' => round((float) ($order?->total ?? 0), 2),
            ];
        })->values();

        $byMethod = $entries
            ->groupBy('payment_method')
            ->map(function (Collection $group, string $method): array {
                $label = $group->first()['payment_method_label'] ?? $method;

                return [
                    'payment_method' => $method,
                    'payment_method_label' => $label,
                    'transactions_count' => $group->count(),
                    'total_amount' => round($group->sum('amount'), 2),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        $dailyTotals = $entries
            ->groupBy('date')
            ->map(function (Collection $group, string $date): array {
                return [
                    'date' => $date,
                    'transactions_count' => $group->count(),
                    'total_amount' => round($group->sum('amount'), 2),
                ];
            })
            ->sortBy('date')
            ->values();

        $platformMethods = [
            PaymentMethod::TalabatPay->value,
            PaymentMethod::JahezPay->value,
            PaymentMethod::Online->value,
        ];

        return [
            'entries' => $entries,
            'summary' => [
                'transactions_count' => $entries->count(),
                'total_amount' => round($entries->sum('amount'), 2),
                'platform_amount' => round(
                    $entries->whereIn('payment_method', $platformMethods)->sum('amount'),
                    2,
                ),
                'instapay_amount' => round(
                    $entries->where('payment_method', PaymentMethod::InstaPay->value)->sum('amount'),
                    2,
                ),
            ],
            'by_method' => $byMethod,
            'daily_totals' => $dailyTotals,
        ];
    }

    public function getCardPaymentsByTerminal(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $rows = OrderPayment::query()
            ->leftJoin('payment_terminals', 'payment_terminals.id', '=', 'order_payments.terminal_id')
            ->where('order_payments.payment_method', 'card')
            ->whereHas('order', function ($q) use ($dateFrom, $dateTo) {
                BusinessTime::applyUtcDateRange(
                    $q->whereNotIn('status', ['cancelled']),
                    $dateFrom,
                    $dateTo,
                );
            })
            ->selectRaw('
                order_payments.terminal_id,
                COALESCE(payment_terminals.name, "غير محدد") as terminal_name,
                MAX(payment_terminals.bank_name) as bank_name,
                MAX(payment_terminals.code) as terminal_code,
                COUNT(order_payments.id) as transaction_count,
                SUM(order_payments.amount) as total_paid_amount,
                SUM(order_payments.fee_amount) as total_fee_amount,
                SUM(order_payments.net_settlement_amount) as total_net_settlement
            ')
            ->groupBy('order_payments.terminal_id', 'terminal_name')
            ->orderByDesc('total_paid_amount')
            ->get()
            ->map(function ($row) {
                $gross = round((float) $row->total_paid_amount, 2);
                $fee = round((float) $row->total_fee_amount, 2);

                return [
                    'terminal_id' => $row->terminal_id,
                    'terminal_name' => $row->terminal_name,
                    'bank_name' => $row->bank_name,
                    'terminal_code' => $row->terminal_code,
                    'transaction_count' => (int) $row->transaction_count,
                    'total_paid_amount' => $gross,
                    'total_fee_amount' => $fee,
                    'total_net_settlement' => round((float) $row->total_net_settlement, 2),
                    'effective_fee_rate' => $gross > 0 ? round(($fee / $gross) * 100, 2) : 0.0,
                ];
            });

        return [
            'terminals' => $rows,
            'totals' => [
                'terminal_count' => $rows->count(),
                'transaction_count' => $rows->sum('transaction_count'),
                'total_paid_amount' => round($rows->sum('total_paid_amount'), 2),
                'total_fee_amount' => round($rows->sum('total_fee_amount'), 2),
                'total_net_settlement' => round($rows->sum('total_net_settlement'), 2),
            ],
        ];
    }

    public function getDrawersReconciliation(?string $dateFrom = null, ?string $dateTo = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = CashierDrawerSession::with(['cashier:id,name', 'posDevice:id,name'])
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at');

        return BusinessTime::applyUtcDateRange($query, $dateFrom, $dateTo, 'ended_at')
            ->paginate($perPage);
    }

    public function getInventoryValuation(): array
    {
        $valuation = InventoryItem::where('is_active', true)
            ->selectRaw('category, SUM(current_stock * unit_cost) as total_value, SUM(current_stock) as total_items')
            ->groupBy('category')
            ->get()
            ->map(function ($item) {
                return [
                    'category'    => $item->category,
                    'total_value' => round((float) $item->total_value, 2),
                    'total_items' => round((float) $item->total_items, 3),
                ];
            });

        $grandTotal = [
            'total_value' => round($valuation->sum('total_value'), 2),
            'total_items' => round($valuation->sum('total_items'), 3),
        ];

        return [
            'breakdown' => $valuation,
            'summary'   => $grandTotal,
        ];
    }

    public function getSalesByShift(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return BusinessTime::applyUtcDateRange(
            Order::query(),
            $dateFrom,
            $dateTo,
        )
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('shift_id, COUNT(*) as total_orders, SUM(total) as gross_revenue, SUM(refund_amount) as total_refunds, SUM(total) - SUM(refund_amount) as net_revenue')
            ->groupBy('shift_id')
            ->orderByDesc('gross_revenue')
            ->with('shift:id,shift_number,started_at,ended_at')
            ->get();
    }

    public function getSalesByCashier(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return BusinessTime::applyUtcDateRange(
            Order::query(),
            $dateFrom,
            $dateTo,
        )
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('cashier_id, COUNT(*) as total_orders, SUM(total) as gross_revenue, SUM(refund_amount) as total_refunds, SUM(total) - SUM(refund_amount) as net_revenue')
            ->groupBy('cashier_id')
            ->orderByDesc('gross_revenue')
            ->with('cashier:id,name')
            ->get();
    }

    public function getExpensesReport(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Expense::with(['category:id,name', 'approver:id,name'])
            ->when($dateFrom, fn ($q, $date) => $q->whereDate('expense_date', '>=', $date))
            ->when($dateTo, fn ($q, $date) => $q->whereDate('expense_date', '<=', $date));

        $expenses = $query->orderByDesc('expense_date')->get();

        $byCategory = $expenses->groupBy('category.name')->map(fn ($group) => [
            'count'  => $group->count(),
            'total'  => round($group->sum('amount'), 2),
        ]);

        $totals = [
            'total_expenses'  => $expenses->count(),
            'total_amount'    => round($expenses->sum('amount'), 2),
            'approved_amount' => round($expenses->whereNotNull('approved_by')->sum('amount'), 2),
            'pending_amount'  => round($expenses->whereNull('approved_by')->sum('amount'), 2),
        ];

        return [
            'expenses'   => $expenses,
            'byCategory' => $byCategory,
            'totals'     => $totals,
        ];
    }

    public function getCashVarianceReport(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return BusinessTime::applyUtcDateRange(
            CashierDrawerSession::with(['cashier:id,name', 'shift:id,shift_number', 'posDevice:id,name']),
            $dateFrom,
            $dateTo,
            'ended_at',
        )
            ->whereNotNull('ended_at')
            ->whereRaw('ABS(cash_difference) > 0')
            ->orderByRaw('ABS(cash_difference) DESC')
            ->get();
    }

    public function getInventoryMovements(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return BusinessTime::applyUtcDateRange(
            InventoryTransaction::with('inventoryItem:id,name,sku,category,unit'),
            $dateFrom,
            $dateTo,
        )
            ->selectRaw('inventory_item_id, type, SUM(quantity) as total_quantity, SUM(total_cost) as total_cost')
            ->groupBy('inventory_item_id', 'type')
            ->get()
            ->groupBy('inventory_item_id')
            ->map(function ($transactionsByItem) {
                $item = $transactionsByItem->first()->inventoryItem;
                $summary = [];
                foreach ($transactionsByItem as $trx) {
                    $summary[$trx->type->value] = [
                        'quantity' => round((float) $trx->total_quantity, 3),
                        'cost'     => round((float) $trx->total_cost, 2),
                    ];
                }
                return [
                    'item'      => $item,
                    'movements' => $summary,
                ];
            })
            ->values();
    }
}
