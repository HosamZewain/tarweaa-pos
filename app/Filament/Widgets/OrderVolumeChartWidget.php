<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OrderVolumeChartWidget extends ChartWidget
{
    protected ?string $heading = 'عدد الطلبات خلال آخر 14 يومًا';
    protected ?string $description = 'مقارنة يومية لحركة الطلبات في آخر أسبوعين.';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 8;
    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('d/m');
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
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'maxBarThickness' => 26,
                ],
            ],
            'labels' => $labels,
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
