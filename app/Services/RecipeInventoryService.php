<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class RecipeInventoryService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryLocationService $inventoryLocationService,
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

                $consumptionLocation = $this->resolveConsumptionLocationForItem($inventoryItem, $actorId);

                $this->inventoryService->deductStock(
                    item: $inventoryItem,
                    quantity: $baseQuantity,
                    actorId: $actorId,
                    type: InventoryTransactionType::SaleDeduction,
                    refType: 'order_item',
                    refId: $item->id,
                    notes: "خصم مكونات وصفة {$item->item_name} للطلب {$item->order->order_number}",
                    location: $consumptionLocation,
                    updateGlobalStock: true,
                );
            }

            $item->update([
                'stock_deducted_at' => now(),
                'updated_by' => $actorId,
            ]);
        });
    }

    private function resolveConsumptionLocationForItem(InventoryItem $inventoryItem, int $actorId): ?InventoryLocation
    {
        $location = $this->inventoryLocationService->defaultRecipeDeductionLocation();

        if (!$location) {
            return null;
        }

        $existingStock = $inventoryItem->locationStocks()
            ->where('inventory_location_id', $location->id)
            ->first();

        if ($existingStock) {
            return $location;
        }

        if ($inventoryItem->locationStocks()->count() === 0) {
            InventoryLocationStock::query()->create([
                'inventory_item_id' => $inventoryItem->id,
                'inventory_location_id' => $location->id,
                'current_stock' => $inventoryItem->current_stock,
                'minimum_stock' => $inventoryItem->minimum_stock,
                'maximum_stock' => $inventoryItem->maximum_stock,
                'unit_cost' => $inventoryItem->unit_cost,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $location;
        }

        return null;
    }
}
