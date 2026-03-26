<?php

namespace App\Services;

use App\Models\CashierDrawerSession;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getDailySales(string $dateFrom, string $dateTo): array
    {
        $orders = Order::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as total_orders,
                SUM(subtotal) as subtotal,
                SUM(discount_amount) as total_discounts,
                SUM(tax_amount) as total_tax,
                SUM(delivery_fee) as total_delivery_fees,
                SUM(total) as gross_revenue,
                SUM(refund_amount) as total_refunds,
                SUM(total) - SUM(refund_amount) as net_revenue
            ")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        $totals = [
            'total_orders'       => $orders->sum('total_orders'),
            'gross_revenue'      => round($orders->sum('gross_revenue'), 2),
            'total_discounts'    => round($orders->sum('total_discounts'), 2),
            'total_tax'          => round($orders->sum('total_tax'), 2),
            'total_delivery_fees' => round($orders->sum('total_delivery_fees'), 2),
            'total_refunds'      => round($orders->sum('total_refunds'), 2),
            'net_revenue'        => round($orders->sum('net_revenue'), 2),
        ];

        return [
            'daily'  => $orders,
            'totals' => $totals,
        ];
    }

    public function getSalesByItem(?string $dateFrom = null, ?string $dateTo = null, int $limit = 50): Collection
    {
        return OrderItem::whereHas('order', function ($q) use ($dateFrom, $dateTo) {
            $q->whereNotIn('status', ['cancelled'])
                ->when($dateFrom, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                ->when($dateTo, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
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
                $q->whereNotIn('status', ['cancelled'])
                    ->when($dateFrom, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($dateTo, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
            })
            ->selectRaw('menu_categories.id as category_id, menu_categories.name as category_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.total) as net_revenue')
            ->groupBy('menu_categories.id', 'menu_categories.name')
            ->orderByDesc('net_revenue')
            ->get();
    }

    public function getSalesByPaymentMethod(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return OrderPayment::whereHas('order', function ($q) use ($dateFrom, $dateTo) {
            $q->whereNotIn('status', ['cancelled'])
                ->when($dateFrom, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                ->when($dateTo, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
        })
            ->selectRaw('payment_method, COUNT(*) as transaction_count, SUM(amount) as total_amount')
            ->groupBy('payment_method')
            ->get();
    }

    public function getDrawersReconciliation(?string $dateFrom = null, ?string $dateTo = null, int $perPage = 25): LengthAwarePaginator
    {
        return CashierDrawerSession::with(['cashier:id,name', 'posDevice:id,name'])
            ->whereNotNull('closed_at')
            ->when($dateFrom, fn ($q, $date) => $q->whereDate('closed_at', '>=', $date))
            ->when($dateTo, fn ($q, $date) => $q->whereDate('closed_at', '<=', $date))
            ->orderByDesc('closed_at')
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
        return Order::query()
            ->whereNotIn('status', ['cancelled'])
            ->when($dateFrom, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($dateTo, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->selectRaw('shift_id, COUNT(*) as total_orders, SUM(total) as gross_revenue, SUM(refund_amount) as total_refunds, SUM(total) - SUM(refund_amount) as net_revenue')
            ->groupBy('shift_id')
            ->orderByDesc('gross_revenue')
            ->with('shift:id,shift_number,started_at,ended_at')
            ->get();
    }

    public function getSalesByCashier(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return Order::whereNotIn('status', ['cancelled'])
            ->when($dateFrom, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($dateTo, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
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
        return CashierDrawerSession::with(['cashier:id,name', 'shift:id,shift_number', 'posDevice:id,name'])
            ->whereNotNull('closed_at')
            ->when($dateFrom, fn ($q, $date) => $q->whereDate('closed_at', '>=', $date))
            ->when($dateTo, fn ($q, $date) => $q->whereDate('closed_at', '<=', $date))
            ->whereRaw('ABS(cash_difference) > 0')
            ->orderByRaw('ABS(cash_difference) DESC')
            ->get();
    }

    public function getInventoryMovements(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return InventoryTransaction::with('inventoryItem:id,name,sku,category,unit')
            ->when($dateFrom, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($dateTo, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
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
