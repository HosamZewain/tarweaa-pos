<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OrderVolumeChartWidget extends ChartWidget
{
    protected ?string $heading = 'حجم الطلبات (١٤ يوم)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 8;

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('M d');
            $data[] = (int) Order::whereDate('created_at', $date)
                ->whereNotIn('status', ['cancelled'])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'عدد الطلبات',
                    'data' => $data,
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
