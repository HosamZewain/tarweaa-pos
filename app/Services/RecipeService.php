<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\MenuItemRecipeLine;

class RecipeService
{
    public function calculateBaseQuantity(float $quantity, float $conversionRate = 1): float
    {
        return round($quantity * $conversionRate, 6);
    }

    public function calculateLineCost(
        float $quantity,
        float $conversionRate = 1,
        ?InventoryItem $inventoryItem = null,
    ): float {
        if (!$inventoryItem) {
            return 0.0;
        }

        return round(
            $this->calculateBaseQuantity($quantity, $conversionRate) * (float) ($inventoryItem->unit_cost ?? 0),
            2,
        );
    }

    public function calculateRecipeCost(MenuItem $menuItem): float
    {
        $menuItem->loadMissing('recipeLines.inventoryItem');

        return round(
            $menuItem->recipeLines->sum(fn (MenuItemRecipeLine $line) => $line->lineCost()),
            2,
        );
    }

    public function calculateRecipeCostFromState(array $recipeLines): float
    {
        $total = 0;
        $inventoryItems = InventoryItem::query()
            ->whereIn(
                'id',
                collect($recipeLines)
                    ->pluck('inventory_item_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->all(),
            )
            ->get()
            ->keyBy('id');

        foreach ($recipeLines as $line) {
            $inventoryItemId = $line['inventory_item_id'] ?? null;

            if (!$inventoryItemId) {
                continue;
            }

            $inventoryItem = $inventoryItems->get((int) $inventoryItemId);

            $total += $this->calculateLineCost(
                quantity: (float) ($line['quantity'] ?? 0),
                conversionRate: (float) ($line['unit_conversion_rate'] ?? 1),
                inventoryItem: $inventoryItem,
            );
        }

        return round($total, 2);
    }

    public function calculateFoodCostPercentage(float $recipeCost, float $sellingPrice): float
    {
        if ($sellingPrice <= 0) {
            return 0.0;
        }

        return round(($recipeCost / $sellingPrice) * 100, 2);
    }

    public function calculateProfitMarginAmount(float $recipeCost, float $sellingPrice): float
    {
        return round($sellingPrice - $recipeCost, 2);
    }

    public function calculateProfitMarginPercentage(float $recipeCost, float $sellingPrice): float
    {
        if ($sellingPrice <= 0) {
            return 0.0;
        }

        return round((($sellingPrice - $recipeCost) / $sellingPrice) * 100, 2);
    }

    public function syncMenuItemCachedCost(MenuItem $menuItem): void
    {
        $menuItem->loadMissing('recipeLines.inventoryItem');

        if ($menuItem->recipeLines->isEmpty()) {
            return;
        }

        $menuItem->forceFill([
            'cost_price' => $this->calculateRecipeCost($menuItem),
        ])->saveQuietly();
    }

    public function syncMenuItemCostsForInventoryItem(InventoryItem $inventoryItem): void
    {
        MenuItem::query()
            ->whereHas('recipeLines', fn ($query) => $query->where('inventory_item_id', $inventoryItem->id))
            ->with('recipeLines.inventoryItem')
            ->get()
            ->each(fn (MenuItem $menuItem) => $this->syncMenuItemCachedCost($menuItem));
    }
}
