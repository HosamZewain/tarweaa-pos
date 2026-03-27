<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\Widget;

class TopSellingItemsWidget extends Widget
{
    protected static ?int $sort = 2;
    protected string $view = 'filament.widgets.top-selling-items';
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $items = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->whereDate('created_at', today())
                ->whereNotIn('status', ['cancelled'])
            )
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
