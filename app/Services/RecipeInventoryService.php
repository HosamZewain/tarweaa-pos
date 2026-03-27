<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class RecipeInventoryService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function shouldDeductForOrder(Order $order): bool
    {
        return $order->payment_status->isPaid();
    }

    public function deductPendingForOrder(Order $order, int $actorId): void
    {
        $order->loadMissing('items.menuItem.recipeLines.inventoryItem');

        foreach ($order->items as $item) {
            if ($item->status->value === 'cancelled') {
                continue;
            }

            $this->deductForOrderItem($item, $actorId);
        }
    }

    public function deductForOrderItem(OrderItem $orderItem, int $actorId): void
    {
        DB::transaction(function () use ($orderItem, $actorId): void {
            $item = OrderItem::query()
                ->lockForUpdate()
                ->with(['order', 'menuItem.recipeLines.inventoryItem'])
                ->findOrFail($orderItem->id);

            if ($item->stock_deducted_at !== null) {
                return;
            }

            $menuItem = $item->menuItem;
            if (!$menuItem || $menuItem->recipeLines->isEmpty()) {
                return;
            }

            foreach ($menuItem->recipeLines as $recipeLine) {
                $inventoryItem = $recipeLine->inventoryItem;
                if (!$inventoryItem) {
                    continue;
                }

                $baseQuantity = round($recipeLine->baseQuantity() * (float) $item->quantity, 6);

                if ($baseQuantity <= 0) {
                    continue;
                }

                $this->inventoryService->deductStock(
                    item: $inventoryItem,
                    quantity: $baseQuantity,
                    actorId: $actorId,
                    type: InventoryTransactionType::SaleDeduction,
                    refType: 'order_item',
                    refId: $item->id,
                    notes: "خصم مكونات وصفة {$item->item_name} للطلب {$item->order->order_number}",
                );
            }

            $item->update([
                'stock_deducted_at' => now(),
                'updated_by' => $actorId,
            ]);
        });
    }
}
