<?php

namespace App\Filament\Widgets;

use App\Models\MenuCategory;
use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CategorySalesChartWidget extends ChartWidget
{
    protected ?string $heading = 'المبيعات حسب الفئة (هذا الشهر)';
    protected int | string | array $columnSpan = 4;

    protected function getData(): array
    {
        $stats = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->where('orders.created_at', '>=', today()->startOfMonth())
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', ['cancelled'])
            ->groupBy('menu_categories.name')
            ->selectRaw('menu_categories.name, SUM(order_items.total) as total_revenue')
            ->pluck('total_revenue', 'name');

        return [
            'datasets' => [
                [
                    'label' => 'المبيعات (ج.م)',
                    'data' => $stats->values()->toArray(),
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#14b8a6'
                    ],
                ],
            ],
            'labels' => $stats->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
