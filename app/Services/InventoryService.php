<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationStock;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private readonly RecipeService $recipeService,
        private readonly InventoryLocationService $inventoryLocationService,
    ) {}

    /**
     * Add stock and record the transaction.
     * Always runs inside a DB transaction with row-level lock.
     */
    public function addStock(
        InventoryItem          $item,
        float                  $quantity,
        int                    $actorId,
        InventoryTransactionType $type      = InventoryTransactionType::Purchase,
        ?float                 $unitCost   = null,
        ?string                $refType    = null,
        ?int                   $refId      = null,
        ?string                $notes      = null,
        ?InventoryLocation     $location   = null,
        bool                   $updateGlobalStock = true,
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('الكمية يجب أن تكون موجبة');
        }

        if (!$updateGlobalStock && !$location) {
            throw new \InvalidArgumentException('يجب تحديد موقع عند تعطيل تحديث الرصيد العام.');
        }

        return DB::transaction(function () use ($item, $quantity, $actorId, $type, $unitCost, $refType, $refId, $notes, $location, $updateGlobalStock) {
            $fresh = $updateGlobalStock
                ? InventoryItem::lockForUpdate()->findOrFail($item->id)
                : InventoryItem::query()->findOrFail($item->id);

            $globalBefore = (float) $fresh->current_stock;
            $globalAfter = $globalBefore;
            $globalAverageCost = (float) ($fresh->unit_cost ?? 0);
            $globalAppliedUnitCost = $unitCost !== null ? (float) $unitCost : $globalAverageCost;

            if ($updateGlobalStock) {
                $globalAfter = $globalBefore + $quantity;

                if ($unitCost !== null) {
                    $currentStockValue = $globalBefore * $globalAverageCost;
                    $incomingStockValue = $quantity * (float) $unitCost;
                    $globalAppliedUnitCost = $globalAfter > 0
                        ? round(($currentStockValue + $incomingStockValue) / $globalAfter, 2)
                        : (float) $unitCost;
                }

                $fresh->update([
                    'current_stock' => $globalAfter,
                    'unit_cost'     => $globalAppliedUnitCost,
                    'updated_by'    => $actorId,
                ]);
            }

            $transactionBefore = $globalBefore;
            $transactionAfter = $globalAfter;
            $transactionUnitCost = $unitCost ?? ($updateGlobalStock ? $fresh->unit_cost : $fresh->unit_cost);

            if ($location) {
                $locationStock = $this->lockLocationStock($fresh, $location, $actorId);
                $locationBefore = (float) $locationStock->current_stock;
                $locationAfter = $locationBefore + $quantity;
                $locationCurrentUnitCost = (float) ($locationStock->unit_cost ?? $fresh->unit_cost ?? 0);
                $locationAppliedUnitCost = $unitCost !== null ? (float) $unitCost : $locationCurrentUnitCost;

                if ($unitCost !== null) {
                    $locationStockValue = $locationBefore * $locationCurrentUnitCost;
                    $incomingStockValue = $quantity * (float) $unitCost;
                    $locationAppliedUnitCost = $locationAfter > 0
                        ? round(($locationStockValue + $incomingStockValue) / $locationAfter, 2)
                        : (float) $unitCost;
                }

                $locationStock->update([
                    'current_stock' => $locationAfter,
                    'unit_cost' => $locationAppliedUnitCost,
                    'updated_by' => $actorId,
                ]);

                $transactionBefore = $locationBefore;
                $transactionAfter = $locationAfter;
                $transactionUnitCost = $unitCost ?? $locationStock->unit_cost;
            }

            $transaction = $fresh->transactions()->create([
                'inventory_location_id' => $location?->id,
                'type'            => $type,
                'quantity'        => $quantity,
                'quantity_before' => $transactionBefore,
                'quantity_after'  => $transactionAfter,
                'unit_cost'       => $transactionUnitCost,
                'total_cost'      => $unitCost ? $quantity * $unitCost : null,
                'reference_type'  => $refType,
                'reference_id'    => $refId,
                'notes'           => $notes,
                'performed_by'    => $actorId,
                'created_by'      => $actorId,
                'updated_by'      => $actorId,
            ]);

            if ($updateGlobalStock || $this->shouldSyncRecipeCostsForLocation($location)) {
                $item->refresh();
                $this->recipeService->syncMenuItemCostsForInventoryItem($fresh->fresh());
            }

            return $transaction;
        });
    }

    /**
     * Deduct stock and record the transaction.
     */
    public function deductStock(
        InventoryItem          $item,
        float                  $quantity,
        int                    $actorId,
        InventoryTransactionType $type    = InventoryTransactionType::SaleDeduction,
        ?string                $refType  = null,
        ?int                   $refId    = null,
        ?string                $notes    = null,
        ?InventoryLocation     $location = null,
        bool                   $updateGlobalStock = true,
        ?float                 $unitCostOverride = null,
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('الكمية يجب أن تكون موجبة');
        }

        if (!$updateGlobalStock && !$location) {
            throw new \InvalidArgumentException('يجب تحديد موقع عند تعطيل تحديث الرصيد العام.');
        }

        return DB::transaction(function () use ($item, $quantity, $actorId, $type, $refType, $refId, $notes, $location, $updateGlobalStock, $unitCostOverride) {
            $fresh = $updateGlobalStock
                ? InventoryItem::lockForUpdate()->findOrFail($item->id)
                : InventoryItem::query()->findOrFail($item->id);

            $transactionBefore = 0.0;
            $transactionAfter = 0.0;
            $transactionUnitCost = $unitCostOverride ?? (float) $fresh->unit_cost;
            $transactionNotes = $notes;
            $negativeContexts = [];
            $allowNegativeSaleDeduction = $this->shouldAllowNegativeSaleDeduction(
                type: $type,
                location: $location,
            );

            if ($location) {
                $locationStock = $this->lockLocationStock($fresh, $location, $actorId);

                if ((float) $locationStock->current_stock < $quantity && !$allowNegativeSaleDeduction) {
                    throw new \RuntimeException(
                        "مخزون الموقع غير كافٍ. المتاح: {$locationStock->current_stock} {$fresh->unit}"
                    );
                }

                $transactionBefore = (float) $locationStock->current_stock;
                $transactionAfter = $transactionBefore - $quantity;
                $transactionUnitCost = $unitCostOverride ?? (float) ($locationStock->unit_cost ?? $fresh->unit_cost);

                if ($transactionAfter < 0 && $allowNegativeSaleDeduction) {
                    $negativeContexts[] = "الموقع {$location->name}: {$transactionBefore} -> {$transactionAfter}";
                }

                $locationStock->update([
                    'current_stock' => $transactionAfter,
                    'updated_by' => $actorId,
                ]);
            }

            if ($updateGlobalStock) {
                if ((float) $fresh->current_stock < $quantity && !$allowNegativeSaleDeduction) {
                    throw new \RuntimeException(
                        "المخزون غير كافٍ. المتاح: {$fresh->current_stock} {$fresh->unit}"
                    );
                }

                $globalBefore = (float) $fresh->current_stock;
                $globalAfter  = $globalBefore - $quantity;

                if ($globalAfter < 0 && $allowNegativeSaleDeduction) {
                    $negativeContexts[] = "الإجمالي: {$globalBefore} -> {$globalAfter}";
                }

                $fresh->update([
                    'current_stock' => $globalAfter,
                    'updated_by'    => $actorId,
                ]);

                if (!$location) {
                    $transactionBefore = $globalBefore;
                    $transactionAfter = $globalAfter;
                }

                $item->refresh();
            }

            if ($negativeContexts !== []) {
                $negativeNote = 'خصم بيع بالسالب مسموح: ' . implode(' | ', $negativeContexts);
                $transactionNotes = filled($transactionNotes)
                    ? "{$transactionNotes} | {$negativeNote}"
                    : $negativeNote;
            }

            return $fresh->transactions()->create([
                'inventory_location_id' => $location?->id,
                'type'            => $type,
                'quantity'        => -$quantity,   // negative = out
                'quantity_before' => $transactionBefore,
                'quantity_after'  => $transactionAfter,
                'unit_cost'       => $transactionUnitCost,
                'total_cost'      => $transactionUnitCost * $quantity,
                'reference_type'  => $refType,
                'reference_id'    => $refId,
                'notes'           => $transactionNotes,
                'performed_by'    => $actorId,
                'created_by'      => $actorId,
                'updated_by'      => $actorId,
            ]);
        });
    }

    /**
     * Manual inventory adjustment (e.g., after physical count).
     */
    public function adjustTo(
        InventoryItem $item, 
        float         $newQuantity, 
        int           $actorId,
        string        $notes = ''
    ): InventoryTransaction {
        return DB::transaction(function () use ($item, $newQuantity, $actorId, $notes) {
            $fresh  = InventoryItem::lockForUpdate()->findOrFail($item->id);
            $before = (float) $fresh->current_stock;
            $diff   = $newQuantity - $before;

            $fresh->update([
                'current_stock' => $newQuantity,
                'updated_by'    => $actorId,
            ]);
            
            $item->refresh();

            return $fresh->transactions()->create([
                'type'            => InventoryTransactionType::Adjustment,
                'quantity'        => $diff,
                'quantity_before' => $before,
                'quantity_after'  => $newQuantity,
                'notes'           => $notes ?: "تعديل يدوي من {$before} إلى {$newQuantity}",
                'performed_by'    => $actorId,
                'created_by'      => $actorId,
                'updated_by'      => $actorId,
            ]);
        });
    }

    /**
     * Manual adjustment for a specific location while keeping the global balance aligned.
     */
    public function adjustLocationTo(
        InventoryItem $item,
        InventoryLocation $location,
        float $newQuantity,
        int $actorId,
        string $notes = '',
    ): InventoryTransaction {
        if ($newQuantity < 0) {
            throw new \InvalidArgumentException('الكمية الجديدة لا يمكن أن تكون سالبة');
        }

        return DB::transaction(function () use ($item, $location, $newQuantity, $actorId, $notes) {
            $fresh = InventoryItem::lockForUpdate()->findOrFail($item->id);
            $locationStock = $this->lockLocationStock($fresh, $location, $actorId);

            $locationBefore = (float) $locationStock->current_stock;
            $locationAfter = (float) $newQuantity;
            $diff = $locationAfter - $locationBefore;

            $globalBefore = (float) $fresh->current_stock;
            $globalAfter = $globalBefore + $diff;

            if ($globalAfter < 0) {
                throw new \RuntimeException('الرصيد العام لا يسمح بهذا التعديل على الموقع.');
            }

            $locationStock->update([
                'current_stock' => $locationAfter,
                'updated_by' => $actorId,
            ]);

            $fresh->update([
                'current_stock' => $globalAfter,
                'updated_by' => $actorId,
            ]);

            return $fresh->transactions()->create([
                'inventory_location_id' => $location->id,
                'type' => InventoryTransactionType::Adjustment,
                'quantity' => $diff,
                'quantity_before' => $locationBefore,
                'quantity_after' => $locationAfter,
                'unit_cost' => (float) ($locationStock->unit_cost ?? $fresh->unit_cost),
                'notes' => $notes ?: "تعديل رصيد موقع {$location->name} من {$locationBefore} إلى {$locationAfter}",
                'performed_by' => $actorId,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        });
    }

    private function lockLocationStock(InventoryItem $item, InventoryLocation $location, int $actorId): InventoryLocationStock
    {
        $stock = InventoryLocationStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $location->id)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        return InventoryLocationStock::query()->create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $location->id,
            'current_stock' => 0,
            'minimum_stock' => $item->minimum_stock,
            'maximum_stock' => $item->maximum_stock,
            'unit_cost' => $item->unit_cost,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    private function shouldSyncRecipeCostsForLocation(?InventoryLocation $location): bool
    {
        if (!$location) {
            return false;
        }

        return $location->id === $this->inventoryLocationService->defaultRecipeDeductionLocation()?->id;
    }

    private function shouldAllowNegativeSaleDeduction(
        InventoryTransactionType $type,
        ?InventoryLocation $location,
    ): bool {
        if ($type !== InventoryTransactionType::SaleDeduction || !$location) {
            return false;
        }

        $defaultRecipeLocationId = $this->inventoryLocationService->defaultRecipeDeductionLocation()?->id
            ?? $this->inventoryLocationService->restaurant()?->id;

        return $defaultRecipeLocationId !== null && $location->id === $defaultRecipeLocationId;
    }
}
