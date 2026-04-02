<?php

namespace App\Filament\Widgets;

use App\Enums\DrawerSessionStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierDrawerSession;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Shift;
use App\Support\BusinessTime;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    protected ?string $heading = 'مؤشرات التشغيل السريعة';
    protected ?string $description = 'ملخص لحظي لحالة التشغيل خلال اليوم والشهر الحالي.';
    protected int | array | null $columns = [
        'md' => 2,
        'xl' => 3,
    ];

    protected function getStats(): array
    {
        $canViewAnalytics = auth()->user()?->canViewDashboardAnalytics() ?? false;
        $today = BusinessTime::today();
        $yesterday = $today->copy()->subDay();

        $todayOrders = BusinessTime::applyUtcDate(Order::query(), $today)
            ->revenueReportable();

        $yesterdayRevenue = (float) BusinessTime::applyUtcDate(Order::query(), $yesterday)
            ->revenueReportable()
            ->sum('total');

        $todayRevenue    = (float) $todayOrders->sum('total');
        $todayOrderCount = $todayOrders->count();

        $weeklyRevenueTrend = collect(range(6, 0))
            ->map(function (int $offset) use ($today): float {
                return (float) BusinessTime::applyUtcDate(Order::query(), $today->copy()->subDays($offset))
                    ->revenueReportable()
                    ->sum('total');
            })
            ->all();

        $weeklyOrderTrend = collect(range(6, 0))
            ->map(function (int $offset) use ($today): int {
                return (int) BusinessTime::applyUtcDate(Order::query(), $today->copy()->subDays($offset))
                    ->revenueReportable()
                    ->count();
            })
            ->all();

        $activeShift   = Shift::where('status', ShiftStatus::Open)->first();
        $openDrawers   = CashierDrawerSession::where('status', DrawerSessionStatus::Open)->count();
        $lowStockCount = InventoryItem::active()->lowStock()->count();

        $pendingExpenses = Expense::whereNull('approved_by')
            ->whereDate('expense_date', '>=', $today->copy()->startOfMonth())
            ->count();

        $todayAOV = $todayOrderCount > 0 ? $todayRevenue / $todayOrderCount : 0;

        [$monthStart, $monthEnd] = BusinessTime::utcRangeForLocalMonth($today);
        $thisMonthRevenue = (float) Order::whereBetween('created_at', [$monthStart, $monthEnd])
            ->revenueReportable()
            ->sum('total');

        [$lastMonthStart, $lastMonthEnd] = BusinessTime::utcRangeForLocalMonth($today->copy()->subMonthNoOverflow());
        $lastMonthRevenue = (float) Order::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->revenueReportable()
            ->sum('total');

        $thisMonthExpenses = (float) Expense::where('expense_date', '>=', $today->copy()->startOfMonth()->toDateString())
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

        $stats = [];

        if ($canViewAnalytics) {
            $stats[] = Stat::make('مبيعات اليوم', number_format($todayRevenue, 2) . ' ج.م')
                ->description($revenueDesc)
                ->descriptionIcon($todayRevenue >= $yesterdayRevenue ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueColor)
                ->icon('heroicon-o-banknotes')
                ->chart($weeklyRevenueTrend)
                ->chartColor($revenueColor);

            $stats[] = Stat::make('متوسط الطلب (اليوم)', number_format($todayAOV, 2) . ' ج.م')
                ->description('متوسط قيمة الفاتورة')
                ->color('success')
                ->icon('heroicon-o-presentation-chart-line');

            $stats[] = Stat::make('مبيعات الشهر', number_format($thisMonthRevenue, 2) . ' ج.م')
                ->description($monthDesc)
                ->color($monthColor)
                ->icon('heroicon-o-calendar-days');

            $stats[] = Stat::make('صافي الربح (الشهر)', number_format($netProfit, 2) . ' ج.م')
                ->description('المبيعات - المصروفات المعتمدة')
                ->color($netProfit >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-currency-dollar');

            $stats[] = Stat::make('طلبات اليوم', number_format($todayOrderCount))
                ->description('طلبات مدفوعة وغير ملغاة')
                ->color('info')
                ->icon('heroicon-o-shopping-cart')
                ->chart($weeklyOrderTrend)
                ->chartColor('info');
        }

        $stats[] = Stat::make('مخزون منخفض', $lowStockCount)
            ->description($lowStockCount > 0 ? "{$lowStockCount} مادة تحت الحد الأدنى" : 'المخزون في الوضع الطبيعي')
            ->color($lowStockCount > 0 ? 'danger' : 'success')
            ->icon('heroicon-o-exclamation-triangle');

        $stats[] = Stat::make('الوردية النشطة', $activeShift ? '#' . $activeShift->shift_number : 'لا توجد')
            ->description($activeShift ? 'مفتوحة منذ ' . $activeShift->started_at->diffForHumans() : 'لا توجد وردية مفتوحة')
            ->color($activeShift ? 'success' : 'warning')
            ->icon('heroicon-o-clock');

        $stats[] = Stat::make('أدراج مفتوحة', $openDrawers)
            ->description($openDrawers > 0 ? ($openDrawers === 1 ? 'درج نشط' : 'أدراج نشطة') : 'لا توجد أدراج مفتوحة')
            ->color($openDrawers > 0 ? 'info' : 'gray')
            ->icon('heroicon-o-inbox');

        $stats[] = Stat::make('مصروفات معلقة', $pendingExpenses)
            ->description('بانتظار اعتماد هذا الشهر')
            ->color($pendingExpenses > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-receipt-percent');

        return $stats;
    }
}
