<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesChartWidget extends ChartWidget
{
    protected ?string $heading = 'اتجاه المبيعات خلال آخر 14 يومًا';
    protected ?string $description = 'صافي قيمة الطلبات غير الملغاة يومًا بيوم.';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 8;
    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('d/m');
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
                    'borderWidth' => 3,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b98133',
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
            'elements' => [
                'line' => [
                    'tension' => 0.35,
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
        return 'line';
    }
}
