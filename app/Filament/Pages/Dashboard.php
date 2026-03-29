<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPagePermission;
use App\Filament\Widgets\CategorySalesChartWidget;
use App\Filament\Widgets\DashboardHeroWidget;
use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\OrderStatusChartWidget;
use App\Filament\Widgets\OrderVolumeChartWidget;
use App\Filament\Widgets\SalesChartWidget;
use App\Filament\Widgets\TopSellingItemsWidget;
use App\Support\BusinessTime;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    use HasPagePermission;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';
    protected static string | \UnitEnum | null $navigationGroup = 'لوحة التحكم';
    protected static ?string $navigationLabel = 'الرئيسية';
    protected static ?int $navigationSort = -2;
    protected static string $permissionName = 'dashboard.view';

    public function getTitle(): string | Htmlable
    {
        $hour = BusinessTime::now()->hour;
        $greeting = match(true) {
            $hour >= 5  && $hour < 12 => 'صباح الخير',
            $hour >= 12 && $hour < 17 => 'مساء الخير',
            default                   => 'مساء النور',
        };

        return "{$greeting}، " . auth()->user()->name;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'اليوم ' . BusinessTime::now()->format('Y/m/d');
    }

    public function getColumns(): int | array
    {
        return 12;
    }

    public function getWidgets(): array
    {
        $widgets = [
            DashboardHeroWidget::class,
            DashboardStatsWidget::class,
        ];

        if (auth()->user()?->canViewDashboardAnalytics()) {
            $widgets = array_merge($widgets, [
                SalesChartWidget::class,
                OrderStatusChartWidget::class,
                OrderVolumeChartWidget::class,
                CategorySalesChartWidget::class,
                TopSellingItemsWidget::class,
            ]);
        }

        return $widgets;
    }
}
