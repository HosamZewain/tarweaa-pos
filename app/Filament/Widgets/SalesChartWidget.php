<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class SalesChartWidget extends ChartWidget
{
    protected ?string $heading = 'اتحاه المبيعات (١٤ يوم)';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 8;

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('M d');
            $data[] = (float) Order::whereDate('created_at', $date)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total');
        }

        return [
            'datasets' => [
                [
                    'label' => 'المبيعات (ج.م)',
                    'data' => $data,
                    'fill' => 'start',
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b98133',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
