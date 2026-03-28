<?php

namespace App\Filament\Widgets;

use App\Enums\DrawerSessionStatus;
use App\Enums\ShiftStatus;
use App\Filament\Pages\ExpensesReport;
use App\Filament\Pages\InventoryReport;
use App\Filament\Pages\SalesReport;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ShiftResource;
use App\Models\CashierDrawerSession;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Shift;
use Filament\Widgets\Widget;

class DashboardHeroWidget extends Widget
{
    protected static ?int $sort = 0;

    protected string $view = 'filament.widgets.dashboard-hero-widget';

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $canViewAnalytics = auth()->user()?->canViewDashboardAnalytics() ?? false;
        $today = today();

        $activeShift = Shift::query()
            ->where('status', ShiftStatus::Open)
            ->first();

        $openDrawers = CashierDrawerSession::query()
            ->where('status', DrawerSessionStatus::Open)
            ->count();

        $lowStockCount = InventoryItem::query()
            ->active()
            ->lowStock()
            ->count();

        $pendingExpenses = Expense::query()
            ->whereNull('approved_by')
            ->whereDate('expense_date', '>=', $today->copy()->startOfMonth())
            ->count();

        $links = collect([
            [
                'label' => 'تقرير المبيعات',
                'description' => 'مراجعة الإيرادات والخصومات والاتجاه اليومي.',
                'url' => $canViewAnalytics && SalesReport::canAccess() ? SalesReport::getUrl() : null,
                'tone' => 'primary',
            ],
            [
                'label' => 'الطلبات',
                'description' => 'متابعة الطلبات وحالاتها من لوحة العمليات.',
                'url' => OrderResource::canAccess() ? OrderResource::getUrl() : null,
                'tone' => 'info',
            ],
            [
                'label' => 'المصروفات',
                'description' => 'اعتماد أو مراجعة المصروفات المسجلة.',
                'url' => ExpenseResource::canAccess() ? ExpenseResource::getUrl() : null,
                'tone' => 'warning',
            ],
            [
                'label' => 'تقرير المخزون',
                'description' => 'فحص قيمة المخزون والتنبيهات الحرجة.',
                'url' => $canViewAnalytics && InventoryReport::canAccess() ? InventoryReport::getUrl() : null,
                'tone' => 'success',
            ],
            [
                'label' => 'الورديات',
                'description' => 'مراجعة الوردية الحالية والإجراءات التشغيلية.',
                'url' => ShiftResource::canAccess() ? ShiftResource::getUrl() : null,
                'tone' => 'neutral',
            ],
            [
                'label' => 'تقرير المصروفات',
                'description' => 'تحليل الاعتمادات والمبالغ حسب الفئات.',
                'url' => $canViewAnalytics && ExpensesReport::canAccess() ? ExpensesReport::getUrl() : null,
                'tone' => 'danger',
            ],
        ])->filter(fn (array $link): bool => filled($link['url']))->values();

        $focusItems = collect([
            [
                'label' => 'حالة الوردية',
                'value' => $activeShift ? 'الوردية #' . $activeShift->shift_number . ' مفتوحة' : 'لا توجد وردية مفتوحة',
                'description' => $activeShift ? 'بدأت ' . $activeShift->started_at->diffForHumans() : 'يلزم فتح وردية قبل بدء التشغيل.',
                'tone' => $activeShift ? 'success' : 'warning',
            ],
            [
                'label' => 'حالة الأدراج',
                'value' => $openDrawers > 0 ? number_format($openDrawers) . ' أدراج مفتوحة' : 'كل الأدراج مغلقة',
                'description' => $openDrawers > 0 ? 'يمكن متابعة التسويات من شاشة الأدراج.' : 'لا توجد جلسات درج مفتوحة الآن.',
                'tone' => $openDrawers > 0 ? 'info' : 'neutral',
            ],
            [
                'label' => 'تنبيهات اليوم',
                'value' => ($lowStockCount + $pendingExpenses) > 0 ? number_format($lowStockCount + $pendingExpenses) . ' عناصر تحتاج متابعة' : 'لا توجد تنبيهات حرجة',
                'description' => 'مخزون منخفض: ' . number_format($lowStockCount) . ' • مصروفات معلقة: ' . number_format($pendingExpenses),
                'tone' => ($lowStockCount + $pendingExpenses) > 0 ? 'danger' : 'success',
            ],
        ]);

        return [
            'activeShift' => $activeShift,
            'openDrawers' => $openDrawers,
            'lowStockCount' => $lowStockCount,
            'pendingExpenses' => $pendingExpenses,
            'links' => $links,
            'focusItems' => $focusItems,
        ];
    }
}
