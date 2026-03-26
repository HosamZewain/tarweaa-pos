<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChartWidget extends ChartWidget
{
    protected ?string $heading = 'توزيع حالات الطلبات (هذا الشهر)';
    protected int | string | array $columnSpan = 4;

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
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
