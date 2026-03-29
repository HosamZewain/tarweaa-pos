<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardAnalyticsVisibility;
use App\Models\OrderItem;
use App\Support\BusinessTime;
use Filament\Widgets\Widget;

class TopSellingItemsWidget extends Widget
{
    use InteractsWithDashboardAnalyticsVisibility;

    protected static ?int $sort = 2;
    protected string $view = 'filament.widgets.top-selling-items';
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $items = OrderItem::query()
            ->whereHas('order', function ($q) {
                BusinessTime::applyUtcDate(
                    $q->whereNotIn('status', ['cancelled']),
                    BusinessTime::today(),
                );
            })
            ->selectRaw('menu_item_id, item_name, SUM(quantity) as total_qty, SUM(total) as total_rev')
            ->groupBy('menu_item_id', 'item_name')
            ->orderByDesc('total_qty')
            ->limit(20)
            ->get();

        return [
            'items' => $items,
            'maxQuantity' => max((float) ($items->max('total_qty') ?? 0), 1),
        ];
    }
}
