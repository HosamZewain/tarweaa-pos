<?php

namespace App\Services;

use App\Models\CashierDrawerSession;
use App\Models\DiscountLog;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchLine;
use App\Models\Purchase;
use App\Enums\InventoryItemType;
use App\Enums\PaymentMethod;
use App\Support\BusinessTime;
use App\Support\HrFeature;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getHrOverview(): array
    {
        $employees = Employee::query()
            ->manageable()
            ->with([
                'roles',
                'employeeProfile',
                'mealBenefitProfile',
                'employeeSalaries',
                'employeePenalties',
            ])
            ->orderBy('name')
            ->get();

        $today = BusinessTime::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $employeesWithCurrentSalary = $employees
            ->map(fn (Employee $employee): array => [
                'employee' => $employee,
                'salary' => $employee->currentEmployeeSalaryRecord(),
            ]);

        $currentSalaries = $employeesWithCurrentSalary
            ->pluck('salary')
            ->filter();

        $activeBenefitProfiles = $employees
            ->pluck('mealBenefitProfile')
            ->filter(fn ($profile) => $profile && $profile->is_active);

        $recentPenalties = $employees
            ->flatMap(fn (Employee $employee) => $employee->employeePenalties)
            ->sortByDesc('penalty_date')
            ->take(10)
            ->values()
            ->map(function ($penalty): array {
                return [
                    'employee_name' => $penalty->employee?->name ?? '—',
                    'job_title' => $penalty->employee?->employeeProfile?->job_title,
                    'penalty_date' => optional($penalty->penalty_date)->format('Y-m-d'),
                    'reason' => $penalty->reason,
                    'amount' => round((float) $penalty->amount, 2),
                    'is_active' => (bool) $penalty->is_active,
                ];
            });

        $roleBreakdown = $employees
            ->map(function (Employee $employee): array {
                $roleLabel = $employee->roles->pluck('display_name')->filter()->first()
                    ?? $employee->roles->pluck('name')->filter()->first()
                    ?? 'بدون دور';

                return [
                    'role_label' => $roleLabel,
                    'is_active' => (bool) $employee->is_active,
                ];
            })
            ->groupBy('role_label')
            ->map(function (Collection $group, string $roleLabel): array {
                return [
                    'role_label' => $roleLabel,
                    'employees_count' => $group->count(),
                    'active_count' => $group->where('is_active', true)->count(),
                ];
            })
            ->sortByDesc('employees_count')
            ->values();

        $salaryRows = $employeesWithCurrentSalary
            ->filter(fn (array $row) => $row['salary'] !== null)
            ->map(function (array $row): array {
                /** @var Employee $employee */
                $employee = $row['employee'];
                $salary = $row['salary'];

                return [
                    'employee_name' => $employee->employeeProfile?->full_name ?: $employee->name,
                    'job_title' => $employee->employeeProfile?->job_title,
                    'amount' => round((float) $salary->amount, 2),
                    'effective_from' => optional($salary->effective_from)->format('Y-m-d'),
                    'effective_to' => optional($salary->effective_to)->format('Y-m-d') ?: 'مستمر',
                ];
            })
            ->sortByDesc('amount')
            ->values();

        $recentHires = $employees
            ->filter(fn (Employee $employee) => $employee->employeeProfile?->hired_at !== null)
            ->sortByDesc(fn (Employee $employee) => $employee->employeeProfile?->hired_at?->timestamp ?? 0)
            ->take(10)
            ->values()
            ->map(function (Employee $employee): array {
                return [
                    'employee_name' => $employee->employeeProfile?->full_name ?: $employee->name,
                    'job_title' => $employee->employeeProfile?->job_title,
                    'hired_at' => optional($employee->employeeProfile?->hired_at)->format('Y-m-d'),
                    'is_active' => (bool) $employee->is_active,
                ];
            });

        $salaryTablesAvailable = HrFeature::hasSalaryTables();
        $penaltyTablesAvailable = HrFeature::hasPenaltyTables();

        return [
            'summary' => [
                'total_employees' => $employees->count(),
                'active_employees' => $employees->where('is_active', true)->count(),
                'inactive_employees' => $employees->where('is_active', false)->count(),
                'employees_with_profiles' => $employees->filter(fn (Employee $employee) => $employee->employeeProfile !== null)->count(),
                'hired_this_month' => $employees->filter(function (Employee $employee) use ($monthStart, $monthEnd): bool {
                    $hiredAt = $employee->employeeProfile?->hired_at;

                    return $hiredAt !== null && $hiredAt->betweenIncluded($monthStart, $monthEnd);
                })->count(),
                'employees_with_current_salary' => $salaryTablesAvailable ? $currentSalaries->count() : 0,
                'employees_without_current_salary' => $salaryTablesAvailable ? max(0, $employees->count() - $currentSalaries->count()) : $employees->count(),
                'total_current_salaries' => $salaryTablesAvailable ? round($currentSalaries->sum(fn ($salary) => (float) $salary->amount), 2) : 0.0,
                'average_current_salary' => $salaryTablesAvailable && $currentSalaries->isNotEmpty()
                    ? round($currentSalaries->avg(fn ($salary) => (float) $salary->amount), 2)
                    : 0.0,
                'active_penalties_count' => $penaltyTablesAvailable ? $employees->sum(fn (Employee $employee) => $employee->activeEmployeePenaltiesCount()) : 0,
                'active_penalties_total' => $penaltyTablesAvailable ? round($employees->sum(fn (Employee $employee) => $employee->activeEmployeePenaltiesTotal()), 2) : 0.0,
                'active_benefit_profiles' => $activeBenefitProfiles->count(),
                'owner_charge_profiles' => $activeBenefitProfiles->where('can_receive_owner_charge_orders', true)->count(),
                'allowance_profiles' => $activeBenefitProfiles->where('monthly_allowance_enabled', true)->count(),
                'free_meal_profiles' => $activeBenefitProfiles->where('free_meal_enabled', true)->count(),
            ],
            'role_breakdown' => $roleBreakdown,
            'current_salaries' => $salaryRows,
            'recent_penalties' => $recentPenalties,
            'recent_hires' => $recentHires,
        ];
    }

    private function revenueOrdersQuery(?string $dateFrom = null, ?string $dateTo = null)
    {
        return BusinessTime::applyUtcDateRange(
            Order::query()->revenueReportable(),
            $dateFrom,
            $dateTo,
        );
    }

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
        $orders = $this->revenueOrdersQuery($dateFrom, $dateTo)->get();

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
                $q->revenueReportable(),
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

    public function getItemsReport(?string $dateFrom = null, ?string $dateTo = null, ?int $menuItemId = null): array
    {
        $items = OrderItem::query()
            ->leftJoin('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->leftJoin('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->whereHas('order', function ($query) use ($dateFrom, $dateTo) {
                return BusinessTime::applyUtcDateRange(
                    $query->revenueReportable(),
                    $dateFrom,
                    $dateTo,
                );
            })
            ->selectRaw('
                order_items.menu_item_id,
                order_items.item_name,
                menu_categories.name as category_name,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.total) as total_revenue,
                SUM(COALESCE(order_items.cost_price, 0) * order_items.quantity) as total_cost,
                COUNT(DISTINCT order_items.order_id) as orders_count
            ')
            ->groupBy('order_items.menu_item_id', 'order_items.item_name', 'menu_categories.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($row): array {
                $quantity = (float) $row->total_quantity;
                $revenue = round((float) $row->total_revenue, 2);
                $cost = round((float) $row->total_cost, 2);

                return [
                    'menu_item_id' => $row->menu_item_id,
                    'item_name' => $row->item_name,
                    'category_name' => $row->category_name,
                    'total_quantity' => $quantity,
                    'total_revenue' => $revenue,
                    'total_cost' => $cost,
                    'gross_profit' => round($revenue - $cost, 2),
                    'orders_count' => (int) $row->orders_count,
                    'average_unit_price' => $quantity > 0 ? round($revenue / $quantity, 2) : 0.0,
                ];
            })
            ->values();

        $selectedItem = $menuItemId ? $this->getSelectedItemReport($menuItemId, $dateFrom, $dateTo) : null;

        return [
            'summary' => [
                'distinct_items_count' => $items->count(),
                'total_quantity' => round($items->sum('total_quantity'), 2),
                'gross_revenue' => round($items->sum('total_revenue'), 2),
                'total_cost' => round($items->sum('total_cost'), 2),
                'gross_profit' => round($items->sum('gross_profit'), 2),
            ],
            'items' => $items,
            'selectedItem' => $selectedItem,
        ];
    }

    public function getSalesByCategory(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return OrderItem::join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->whereHas('order', function ($q) use ($dateFrom, $dateTo) {
                BusinessTime::applyUtcDateRange(
                    $q->revenueReportable(),
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
        $orders = $this->revenueOrdersQuery($dateFrom, $dateTo)
            ->with(['payments', 'settlement'])
            ->get();

        return collect(PaymentMethod::cases())
            ->map(function (PaymentMethod $method) use ($orders): object {
                $transactionCount = $orders
                    ->sum(fn (Order $order) => $order->payments
                        ->filter(fn (OrderPayment $payment) => $payment->payment_method === $method)
                        ->count());

                return (object) [
                    'payment_method' => $method->value,
                    'transaction_count' => $transactionCount,
                    'total_amount' => round(
                        $orders->sum(fn (Order $order) => $order->reportablePaidAmountForMethod($method)),
                        2,
                    ),
                ];
            })
            ->filter(fn (object $row) => $row->transaction_count > 0)
            ->values();
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

    public function getInventoryValuationByLocation(?int $locationId = null): array
    {
        $rows = InventoryLocationStock::query()
            ->with(['inventoryLocation:id,name,type'])
            ->whereHas('inventoryItem', fn ($query) => $query->where('is_active', true))
            ->when($locationId, fn ($query, $id) => $query->where('inventory_location_id', $id))
            ->selectRaw('inventory_location_id, SUM(current_stock * unit_cost) as total_value, SUM(current_stock) as total_items, COUNT(*) as item_count')
            ->groupBy('inventory_location_id')
            ->get()
            ->map(function (InventoryLocationStock $row): array {
                return [
                    'location_id' => $row->inventory_location_id,
                    'location_name' => $row->inventoryLocation?->name ?? '—',
                    'location_type' => $row->inventoryLocation?->type ?? 'other',
                    'total_value' => round((float) $row->total_value, 2),
                    'total_items' => round((float) $row->total_items, 3),
                    'item_count' => (int) $row->item_count,
                ];
            })
            ->values();

        return [
            'rows' => $rows,
            'summary' => [
                'total_value' => round($rows->sum('total_value'), 2),
                'total_items' => round($rows->sum('total_items'), 3),
                'locations_count' => $rows->count(),
            ],
        ];
    }

    public function getStockByLocation(?int $locationId = null): Collection
    {
        return InventoryLocationStock::query()
            ->with(['inventoryLocation:id,name,type', 'inventoryItem:id,name,sku,category,unit,is_active'])
            ->whereHas('inventoryItem', fn ($query) => $query->where('is_active', true))
            ->when($locationId, fn ($query, $id) => $query->where('inventory_location_id', $id))
            ->orderBy('inventory_location_id')
            ->get()
            ->map(function (InventoryLocationStock $stock): array {
                return [
                    'location_id' => $stock->inventory_location_id,
                    'location_name' => $stock->inventoryLocation?->name ?? '—',
                    'location_type' => $stock->inventoryLocation?->type ?? 'other',
                    'item_id' => $stock->inventory_item_id,
                    'item_name' => $stock->inventoryItem?->name ?? '—',
                    'item_sku' => $stock->inventoryItem?->sku,
                    'category' => $stock->inventoryItem?->category,
                    'unit' => $stock->inventoryItem?->unit,
                    'current_stock' => round((float) $stock->current_stock, 3),
                    'minimum_stock' => round((float) $stock->minimum_stock, 3),
                    'maximum_stock' => round((float) $stock->maximum_stock, 3),
                    'unit_cost' => round((float) $stock->unit_cost, 2),
                    'stock_value' => round((float) $stock->current_stock * (float) $stock->unit_cost, 2),
                ];
            })
            ->values();
    }

    public function getLowStockByLocation(?int $locationId = null): Collection
    {
        return InventoryLocationStock::query()
            ->with(['inventoryLocation:id,name,type', 'inventoryItem:id,name,sku,category,unit,is_active'])
            ->whereHas('inventoryItem', fn ($query) => $query->where('is_active', true))
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->when($locationId, fn ($query, $id) => $query->where('inventory_location_id', $id))
            ->orderByRaw('current_stock - minimum_stock ASC')
            ->get()
            ->map(function (InventoryLocationStock $stock): array {
                return [
                    'location_id' => $stock->inventory_location_id,
                    'location_name' => $stock->inventoryLocation?->name ?? '—',
                    'item_name' => $stock->inventoryItem?->name ?? '—',
                    'item_sku' => $stock->inventoryItem?->sku,
                    'category' => $stock->inventoryItem?->category,
                    'unit' => $stock->inventoryItem?->unit,
                    'current_stock' => round((float) $stock->current_stock, 3),
                    'minimum_stock' => round((float) $stock->minimum_stock, 3),
                    'deficit' => round((float) $stock->minimum_stock - (float) $stock->current_stock, 3),
                ];
            })
            ->values();
    }

    public function getStockReconciliation(): array
    {
        $rows = InventoryItem::query()
            ->where('is_active', true)
            ->with(['locationStocks:id,inventory_item_id,current_stock', 'defaultSupplier:id,name'])
            ->orderBy('name')
            ->get()
            ->map(function (InventoryItem $item): array {
                $locationsTotal = round((float) $item->locationStocks->sum('current_stock'), 3);
                $globalStock = round((float) $item->current_stock, 3);
                $variance = round($globalStock - $locationsTotal, 3);

                return [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'item_sku' => $item->sku,
                    'category' => $item->category,
                    'unit' => $item->unit,
                    'global_stock' => $globalStock,
                    'locations_total_stock' => $locationsTotal,
                    'variance' => $variance,
                    'has_variance' => abs($variance) > 0.0005,
                ];
            });

        return [
            'rows' => $rows,
            'variance_rows' => $rows->where('has_variance', true)->values(),
            'summary' => [
                'items_checked' => $rows->count(),
                'mismatched_items' => $rows->where('has_variance', true)->count(),
                'matched_items' => $rows->where('has_variance', false)->count(),
                'global_total_stock' => round($rows->sum('global_stock'), 3),
                'locations_total_stock' => round($rows->sum('locations_total_stock'), 3),
            ],
        ];
    }

    public function getSalesByShift(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return $this->revenueOrdersQuery($dateFrom, $dateTo)
            ->selectRaw('shift_id, COUNT(*) as total_orders, SUM(total) as gross_revenue, SUM(refund_amount) as total_refunds, SUM(total) - SUM(refund_amount) as net_revenue')
            ->groupBy('shift_id')
            ->orderByDesc('gross_revenue')
            ->with('shift:id,shift_number,started_at,ended_at')
            ->get();
    }

    public function getSalesByCashier(?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return $this->revenueOrdersQuery($dateFrom, $dateTo)
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

    private function getSelectedItemReport(int $menuItemId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $menuItem = MenuItem::query()->with('category:id,name')->find($menuItemId);

        $rows = OrderItem::query()
            ->with(['order:id,created_at', 'menuItem.category:id,name'])
            ->where('menu_item_id', $menuItemId)
            ->whereHas('order', function ($query) use ($dateFrom, $dateTo) {
                return BusinessTime::applyUtcDateRange(
                    $query->revenueReportable(),
                    $dateFrom,
                    $dateTo,
                );
            })
            ->get();

        $quantity = (float) $rows->sum('quantity');
        $revenue = round((float) $rows->sum('total'), 2);
        $cost = round($rows->sum(fn (OrderItem $item) => (float) $item->cost_price * (float) $item->quantity), 2);
        $orderCount = $rows->pluck('order_id')->unique()->count();

        $daily = $rows
            ->groupBy(fn (OrderItem $item) => BusinessTime::asLocal($item->order?->created_at)->toDateString())
            ->map(function (Collection $group, string $date): array {
                return [
                    'date' => $date,
                    'total_quantity' => round((float) $group->sum('quantity'), 2),
                    'total_revenue' => round((float) $group->sum('total'), 2),
                    'orders_count' => $group->pluck('order_id')->unique()->count(),
                ];
            })
            ->sortBy('date')
            ->values();

        $variants = $rows
            ->groupBy(fn (OrderItem $item) => $item->variant_name ?: 'الافتراضي')
            ->map(function (Collection $group, string $variantName): array {
                return [
                    'variant_name' => $variantName,
                    'total_quantity' => round((float) $group->sum('quantity'), 2),
                    'total_revenue' => round((float) $group->sum('total'), 2),
                    'orders_count' => $group->pluck('order_id')->unique()->count(),
                ];
            })
            ->sortByDesc('total_revenue')
            ->values();

        $firstSoldAt = $rows->map(fn (OrderItem $item) => $item->order?->created_at)->filter()->sort()->first();
        $lastSoldAt = $rows->map(fn (OrderItem $item) => $item->order?->created_at)->filter()->sortDesc()->first();

        return [
            'summary' => [
                'menu_item_id' => $menuItemId,
                'item_name' => $menuItem?->name ?? ($rows->first()?->item_name ?? '—'),
                'category_name' => $menuItem?->category?->name ?? ($rows->first()?->menuItem?->category?->name),
                'total_quantity' => round($quantity, 2),
                'total_revenue' => $revenue,
                'total_cost' => $cost,
                'gross_profit' => round($revenue - $cost, 2),
                'orders_count' => $orderCount,
                'days_sold_count' => $daily->count(),
                'average_unit_price' => $quantity > 0 ? round($revenue / $quantity, 2) : 0.0,
                'average_order_revenue' => $orderCount > 0 ? round($revenue / $orderCount, 2) : 0.0,
                'first_sold_at' => $firstSoldAt ? BusinessTime::asLocal($firstSoldAt)->format('Y-m-d h:i A') : null,
                'last_sold_at' => $lastSoldAt ? BusinessTime::asLocal($lastSoldAt)->format('Y-m-d h:i A') : null,
            ],
            'daily' => $daily,
            'variants' => $variants,
        ];
    }

    public function getInventoryMovements(?string $dateFrom = null, ?string $dateTo = null, ?int $locationId = null): Collection
    {
        return BusinessTime::applyUtcDateRange(
            InventoryTransaction::with('inventoryItem:id,name,sku,category,unit', 'inventoryLocation:id,name,type'),
            $dateFrom,
            $dateTo,
        )
            ->when($locationId, fn ($query, $id) => $query->where('inventory_location_id', $id))
            ->selectRaw('inventory_item_id, inventory_location_id, type, SUM(quantity) as total_quantity, SUM(total_cost) as total_cost')
            ->groupBy('inventory_item_id', 'inventory_location_id', 'type')
            ->get()
            ->groupBy(fn (InventoryTransaction $transaction) => ($transaction->inventory_location_id ?? 'none') . ':' . $transaction->inventory_item_id)
            ->map(function ($transactionsByItem) {
                $item = $transactionsByItem->first()->inventoryItem;
                $location = $transactionsByItem->first()->inventoryLocation;
                $summary = [];
                foreach ($transactionsByItem as $trx) {
                    $summary[$trx->type->value] = [
                        'quantity' => round((float) $trx->total_quantity, 3),
                        'cost'     => round((float) $trx->total_cost, 2),
                    ];
                }
                return [
                    'location' => $location,
                    'item'      => $item,
                    'movements' => $summary,
                ];
            })
            ->values();
    }

    public function getPreparedItemStockByLocation(?int $locationId = null, ?int $preparedItemId = null): Collection
    {
        return InventoryLocationStock::query()
            ->with(['inventoryLocation:id,name,type', 'inventoryItem:id,name,sku,category,unit,item_type,is_active'])
            ->whereHas('inventoryItem', fn ($query) => $query
                ->where('is_active', true)
                ->where('item_type', InventoryItemType::PreparedItem->value))
            ->when($locationId, fn ($query, $id) => $query->where('inventory_location_id', $id))
            ->when($preparedItemId, fn ($query, $id) => $query->where('inventory_item_id', $id))
            ->orderBy('inventory_location_id')
            ->get()
            ->map(function (InventoryLocationStock $stock): array {
                return [
                    'location_id' => $stock->inventory_location_id,
                    'location_name' => $stock->inventoryLocation?->name ?? '—',
                    'item_id' => $stock->inventory_item_id,
                    'item_name' => $stock->inventoryItem?->name ?? '—',
                    'item_sku' => $stock->inventoryItem?->sku,
                    'category' => $stock->inventoryItem?->category,
                    'unit' => $stock->inventoryItem?->unit,
                    'current_stock' => round((float) $stock->current_stock, 3),
                    'unit_cost' => round((float) $stock->unit_cost, 2),
                    'stock_value' => round((float) $stock->current_stock * (float) $stock->unit_cost, 2),
                ];
            })
            ->values();
    }

    public function getProductionBatchesReport(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $locationId = null,
        ?int $preparedItemId = null,
    ): array {
        $entries = BusinessTime::applyUtcDateRange(
            ProductionBatch::query()
                ->with([
                    'preparedItem:id,name,unit',
                    'productionRecipe:id,name',
                    'location:id,name,type',
                    'producer:id,name',
                    'lines:id,production_batch_id,inventory_item_id,actual_quantity,total_cost,unit',
                    'lines.inventoryItem:id,name,unit',
                ])
                ->where('status', 'completed')
                ->orderByDesc('produced_at'),
            $dateFrom,
            $dateTo,
            'produced_at',
        )
            ->when($locationId, fn ($query, $id) => $query->where('inventory_location_id', $id))
            ->when($preparedItemId, fn ($query, $id) => $query->where('prepared_item_id', $id))
            ->get();

        $byPreparedItem = $entries
            ->groupBy(fn (ProductionBatch $batch) => $batch->preparedItem?->name ?? '—')
            ->map(function (Collection $group, string $itemName): array {
                return [
                    'prepared_item_name' => $itemName,
                    'batches_count' => $group->count(),
                    'total_output_quantity' => round($group->sum('actual_output_quantity'), 3),
                    'total_waste_quantity' => round($group->sum('waste_quantity'), 3),
                    'total_input_cost' => round($group->sum('total_input_cost'), 2),
                    'average_unit_cost' => round($group->avg('unit_cost') ?: 0, 2),
                    'average_yield_variance_percentage' => round($group->avg('yield_variance_percentage') ?: 0, 2),
                ];
            })
            ->sortByDesc('total_input_cost')
            ->values();

        $byLocation = $entries
            ->groupBy(fn (ProductionBatch $batch) => $batch->location?->name ?? '—')
            ->map(function (Collection $group, string $location): array {
                return [
                    'location_name' => $location,
                    'batches_count' => $group->count(),
                    'total_output_quantity' => round($group->sum('actual_output_quantity'), 3),
                    'total_waste_quantity' => round($group->sum('waste_quantity'), 3),
                    'total_input_cost' => round($group->sum('total_input_cost'), 2),
                ];
            })
            ->sortByDesc('total_input_cost')
            ->values();

        return [
            'entries' => $entries,
            'by_prepared_item' => $byPreparedItem,
            'by_location' => $byLocation,
            'summary' => [
                'batches_count' => $entries->count(),
                'total_output_quantity' => round($entries->sum('actual_output_quantity'), 3),
                'total_waste_quantity' => round($entries->sum('waste_quantity'), 3),
                'total_input_cost' => round($entries->sum('total_input_cost'), 2),
                'average_unit_cost' => round($entries->avg('unit_cost') ?: 0, 2),
                'positive_yield_batches' => $entries->filter(fn (ProductionBatch $batch) => (float) $batch->yield_variance_quantity > 0)->count(),
                'negative_yield_batches' => $entries->filter(fn (ProductionBatch $batch) => (float) $batch->yield_variance_quantity < 0)->count(),
            ],
        ];
    }

    public function getProductionRawConsumption(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $locationId = null,
        ?int $preparedItemId = null,
    ): array {
        $entries = BusinessTime::applyUtcDateRange(
            ProductionBatchLine::query()
                ->with([
                    'inventoryItem:id,name,sku,category,unit,item_type',
                    'productionBatch:id,batch_number,prepared_item_id,inventory_location_id,produced_at',
                    'productionBatch.preparedItem:id,name',
                    'productionBatch.location:id,name,type',
                ])
                ->whereHas('productionBatch', fn ($query) => $query->where('status', 'completed'))
                ->orderByDesc('created_at'),
            $dateFrom,
            $dateTo,
            'created_at',
        )
            ->when($locationId, fn ($query, $id) => $query->whereHas('productionBatch', fn ($batchQuery) => $batchQuery->where('inventory_location_id', $id)))
            ->when($preparedItemId, fn ($query, $id) => $query->whereHas('productionBatch', fn ($batchQuery) => $batchQuery->where('prepared_item_id', $id)))
            ->get();

        $byRawItem = $entries
            ->groupBy(fn (ProductionBatchLine $line) => $line->inventoryItem?->name ?? '—')
            ->map(function (Collection $group, string $itemName): array {
                return [
                    'item_name' => $itemName,
                    'unit' => $group->first()?->inventoryItem?->unit ?? '—',
                    'lines_count' => $group->count(),
                    'consumed_quantity' => round($group->sum('base_quantity'), 6),
                    'consumed_cost' => round($group->sum('total_cost'), 2),
                ];
            })
            ->sortByDesc('consumed_cost')
            ->values();

        return [
            'entries' => $entries,
            'by_raw_item' => $byRawItem,
            'summary' => [
                'lines_count' => $entries->count(),
                'consumed_quantity' => round($entries->sum('base_quantity'), 6),
                'consumed_cost' => round($entries->sum('total_cost'), 2),
                'raw_items_count' => $byRawItem->count(),
            ],
        ];
    }

    public function getPurchasesByLocation(?string $dateFrom = null, ?string $dateTo = null, ?int $locationId = null): array
    {
        $purchases = BusinessTime::applyUtcDateRange(
            Purchase::query()
                ->with(['supplier:id,name', 'destinationLocation:id,name,type'])
                ->orderByDesc('created_at'),
            $dateFrom,
            $dateTo,
        )
            ->when($locationId, fn ($query, $id) => $query->where('destination_location_id', $id))
            ->get();

        $byLocation = $purchases
            ->groupBy(fn (Purchase $purchase) => $purchase->destinationLocation?->name ?? '—')
            ->map(function (Collection $group, string $location): array {
                return [
                    'location_name' => $location,
                    'purchases_count' => $group->count(),
                    'received_count' => $group->where('status', 'received')->count(),
                    'total_amount' => round($group->sum('total'), 2),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return [
            'entries' => $purchases,
            'by_location' => $byLocation,
            'summary' => [
                'purchases_count' => $purchases->count(),
                'total_amount' => round($purchases->sum('total'), 2),
                'locations_count' => $byLocation->count(),
            ],
        ];
    }

    public function getReceivedStockByLocation(?string $dateFrom = null, ?string $dateTo = null, ?int $locationId = null): array
    {
        $entries = BusinessTime::applyUtcDateRange(
            InventoryTransaction::query()
                ->with([
                    'inventoryItem:id,name,sku,category,unit',
                    'inventoryLocation:id,name,type',
                    'performer:id,name',
                ])
                ->where('type', 'purchase')
                ->whereNotNull('inventory_location_id')
                ->orderByDesc('created_at'),
            $dateFrom,
            $dateTo,
        )
            ->when($locationId, fn ($query, $id) => $query->where('inventory_location_id', $id))
            ->get();

        $byLocation = $entries
            ->groupBy(fn (InventoryTransaction $entry) => $entry->inventoryLocation?->name ?? '—')
            ->map(function (Collection $group, string $location): array {
                return [
                    'location_name' => $location,
                    'transactions_count' => $group->count(),
                    'received_quantity' => round($group->sum('quantity'), 3),
                    'received_value' => round($group->sum('total_cost'), 2),
                ];
            })
            ->sortByDesc('received_value')
            ->values();

        return [
            'entries' => $entries,
            'by_location' => $byLocation,
            'summary' => [
                'transactions_count' => $entries->count(),
                'received_quantity' => round($entries->sum('quantity'), 3),
                'received_value' => round($entries->sum('total_cost'), 2),
                'locations_count' => $byLocation->count(),
            ],
        ];
    }

    public function getInventoryTransfersReport(?string $dateFrom = null, ?string $dateTo = null, ?int $locationId = null): array
    {
        $transfers = BusinessTime::applyUtcDateRange(
            InventoryTransfer::query()
                ->with([
                    'sourceLocation:id,name,type',
                    'destinationLocation:id,name,type',
                    'requester:id,name',
                    'transferredBy:id,name',
                    'receivedBy:id,name',
                    'items:id,inventory_transfer_id,inventory_item_id,quantity_sent,quantity_received',
                ])
                ->orderByDesc('created_at'),
            $dateFrom,
            $dateTo,
        )
            ->when($locationId, function ($query, $id) {
                $query->where(function ($inner) use ($id) {
                    $inner->where('source_location_id', $id)
                        ->orWhere('destination_location_id', $id);
                });
            })
            ->get();

        $byStatus = $transfers
            ->groupBy('status')
            ->map(function (Collection $group, string $status): array {
                return [
                    'status' => $status,
                    'transfers_count' => $group->count(),
                    'items_count' => $group->sum(fn (InventoryTransfer $transfer) => $transfer->items->count()),
                    'quantity_sent' => round($group->sum(fn (InventoryTransfer $transfer) => $transfer->items->sum('quantity_sent')), 3),
                    'quantity_received' => round($group->sum(fn (InventoryTransfer $transfer) => $transfer->items->sum('quantity_received')), 3),
                ];
            })
            ->values();

        return [
            'entries' => $transfers,
            'by_status' => $byStatus,
            'summary' => [
                'transfers_count' => $transfers->count(),
                'sent_count' => $transfers->where('status', 'sent')->count(),
                'received_count' => $transfers->where('status', 'received')->count(),
                'total_quantity_sent' => round($transfers->sum(fn (InventoryTransfer $transfer) => $transfer->items->sum('quantity_sent')), 3),
                'total_quantity_received' => round($transfers->sum(fn (InventoryTransfer $transfer) => $transfer->items->sum('quantity_received')), 3),
            ],
        ];
    }
}
