<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardAnalyticsVisibility;
use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChartWidget extends ChartWidget
{
    use InteractsWithDashboardAnalyticsVisibility;

    protected ?string $heading = 'حالات الطلبات هذا الشهر';
    protected ?string $description = 'مقارنة سريعة لتوزيع الطلبات حسب حالة المعالجة.';
    protected int | string | array $columnSpan = 4;
    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $stats = Order::where('created_at', '>=', today()->startOfMonth())
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status');

        $labels = [];
        $data = [];
        $backgroundColor = [];

        foreach (OrderStatus::cases() as $status) {
            $labels[] = $status->label();
            $data[] = $stats->get($status->value, 0);
            $backgroundColor[] = match($status->color()) {
                'yellow' => '#fbbf24',
                'blue'   => '#3b82f6',
                'orange' => '#f97316',
                'green'  => '#10b981',
                'purple' => '#8b5cf6',
                'teal'   => '#14b8a6',
                'red'    => '#ef4444',
                'gray'   => '#6b7280',
                default  => '#d1d5db',
            };
        }

        return [
            'datasets' => [
                [
                    'label' => 'الطلبات',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '68%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'rtl' => true,
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 18,
                        'boxWidth' => 10,
                    ],
                ],
                'tooltip' => [
                    'rtl' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
