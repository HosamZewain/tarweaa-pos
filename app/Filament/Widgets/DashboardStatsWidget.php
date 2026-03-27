<?php

namespace App\Filament\Widgets;

use App\Enums\DrawerSessionStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierDrawerSession;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Shift;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    protected ?string $heading = 'مؤشرات التشغيل السريعة';
    protected ?string $description = 'ملخص لحظي للمبيعات، المخزون، الوردية، والأدراج خلال اليوم والشهر الحالي.';
    protected int | array | null $columns = [
        'md' => 2,
        'xl' => 3,
    ];

    protected function getStats(): array
    {
        $today     = today();
        $yesterday = today()->subDay();

        $todayOrders = Order::whereDate('created_at', $today)
            ->whereNotIn('status', ['cancelled']);

        $yesterdayRevenue = (float) Order::whereDate('created_at', $yesterday)
            ->whereNotIn('status', ['cancelled'])
            ->sum('total');

        $todayRevenue    = (float) $todayOrders->sum('total');
        $todayOrderCount = $todayOrders->count();

        $weeklyRevenueTrend = collect(range(6, 0))
            ->map(fn (int $offset): float => (float) Order::whereDate('created_at', today()->subDays($offset))
                ->whereNotIn('status', ['cancelled'])
                ->sum('total'))
            ->all();

        $weeklyOrderTrend = collect(range(6, 0))
            ->map(fn (int $offset): int => (int) Order::whereDate('created_at', today()->subDays($offset))
                ->whereNotIn('status', ['cancelled'])
                ->count())
            ->all();

        $activeShift   = Shift::where('status', ShiftStatus::Open)->first();
        $openDrawers   = CashierDrawerSession::where('status', DrawerSessionStatus::Open)->count();
        $lowStockCount = InventoryItem::active()->lowStock()->count();

        $pendingExpenses = Expense::whereNull('approved_by')
            ->whereDate('expense_date', '>=', today()->startOfMonth())
            ->count();

        $todayAOV = $todayOrderCount > 0 ? $todayRevenue / $todayOrderCount : 0;

        $monthStart = today()->startOfMonth();
        $thisMonthRevenue = (float) Order::where('created_at', '>=', $monthStart)
            ->whereNotIn('status', ['cancelled'])
            ->sum('total');

        $lastMonthStart = today()->subMonth()->startOfMonth();
        $lastMonthEnd = today()->subMonth()->endOfMonth();
        $lastMonthRevenue = (float) Order::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->whereNotIn('status', ['cancelled'])
            ->sum('total');

        $thisMonthExpenses = (float) Expense::where('expense_date', '>=', $monthStart)
            ->whereNotNull('approved_by')
            ->sum('amount');
        
        $netProfit = $thisMonthRevenue - $thisMonthExpenses;

        if ($yesterdayRevenue > 0) {
            $diff = $todayRevenue - $yesterdayRevenue;
            $pct  = round(abs($diff) / $yesterdayRevenue * 100, 1);
            $revenueDesc  = $diff >= 0 ? "↑ {$pct}% عن أمس" : "↓ {$pct}% عن أمس";
            $revenueColor = $diff >= 0 ? 'success' : 'danger';
        } else {
            $revenueDesc  = 'لا توجد مبيعات أمس';
            $revenueColor = 'success';
        }

        if ($lastMonthRevenue > 0) {
            $monthDiff = $thisMonthRevenue - $lastMonthRevenue;
            $monthPct = round(abs($monthDiff) / $lastMonthRevenue * 100, 1);
            $monthDesc = $monthDiff >= 0 ? "↑ {$monthPct}% عن الشهر الماضي" : "↓ {$monthPct}% عن الشهر الماضي";
            $monthColor = $monthDiff >= 0 ? 'success' : 'danger';
        } else {
            $monthDesc = 'بدأ الشهر بأداء جديد';
            $monthColor = 'success';
        }

        return [
            Stat::make('مبيعات اليوم', number_format($todayRevenue, 2) . ' ج.م')
                ->description($revenueDesc)
                ->descriptionIcon($todayRevenue >= $yesterdayRevenue ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueColor)
                ->icon('heroicon-o-banknotes')
                ->chart($weeklyRevenueTrend)
                ->chartColor($revenueColor),

            Stat::make('متوسط الطلب (اليوم)', number_format($todayAOV, 2) . ' ج.م')
                ->description('متوسط قيمة الفاتورة')
                ->color('success')
                ->icon('heroicon-o-presentation-chart-line'),

            Stat::make('مبيعات الشهر', number_format($thisMonthRevenue, 2) . ' ج.م')
                ->description($monthDesc)
                ->color($monthColor)
                ->icon('heroicon-o-calendar-days'),

            Stat::make('صافي الربح (الشهر)', number_format($netProfit, 2) . ' ج.م')
                ->description('المبيعات - المصروفات المعتمدة')
                ->color($netProfit >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-currency-dollar'),

            Stat::make('طلبات اليوم', number_format($todayOrderCount))
                ->description('طلبات مكتملة ونشطة')
                ->color('info')
                ->icon('heroicon-o-shopping-cart')
                ->chart($weeklyOrderTrend)
                ->chartColor('info'),

            Stat::make('مخزون منخفض', $lowStockCount)
                ->description($lowStockCount > 0 ? "{$lowStockCount} مادة تحت الحد الأدنى" : 'المخزون في الوضع الطبيعي')
                ->color($lowStockCount > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            
            Stat::make('الوردية النشطة', $activeShift ? '#' . $activeShift->shift_number : 'لا توجد')
                ->description($activeShift ? 'مفتوحة منذ ' . $activeShift->started_at->diffForHumans() : 'لا توجد وردية مفتوحة')
                ->color($activeShift ? 'success' : 'warning')
                ->icon('heroicon-o-clock'),

            Stat::make('أدراج مفتوحة', $openDrawers)
                ->description($openDrawers > 0 ? ($openDrawers === 1 ? 'درج نشط' : 'أدراج نشطة') : 'لا توجد أدراج مفتوحة')
                ->color($openDrawers > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-inbox'),

            Stat::make('مصروفات معلقة', $pendingExpenses)
                ->description('بانتظار اعتماد هذا الشهر')
                ->color($pendingExpenses > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-receipt-percent'),
        ];
    }
}
