<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardAnalyticsVisibility;
use App\Support\BusinessTime;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CategorySalesChartWidget extends ChartWidget
{
    use InteractsWithDashboardAnalyticsVisibility;

    protected ?string $heading = 'المبيعات حسب الفئة هذا الشهر';
    protected ?string $description = 'أفضل الفئات أداءً بحسب الطلبات المدفوعة وغير الملغاة في الشهر الحالي.';
    protected int | string | array $columnSpan = 4;
    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        [$monthStart, $monthEnd] = BusinessTime::utcRangeForLocalMonth();

        $stats = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->whereBetween('orders.created_at', [$monthStart, $monthEnd])
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', ['cancelled'])
            ->where('orders.payment_status', 'paid')
            ->groupBy('menu_categories.name')
            ->selectRaw('menu_categories.name, SUM(order_items.total) as total_revenue')
            ->pluck('total_revenue', 'name');

        return [
            'datasets' => [
                [
                    'label' => 'المبيعات (ج.م)',
                    'data' => $stats->values()->toArray(),
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'maxBarThickness' => 26,
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#14b8a6'
                    ],
                ],
            ],
            'labels' => $stats->keys()->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'rtl' => true,
                    'displayColors' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(148, 163, 184, 0.12)',
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
