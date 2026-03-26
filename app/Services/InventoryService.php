<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

class InventoryService
{
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
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('الكمية يجب أن تكون موجبة');
        }

        return DB::transaction(function () use ($item, $quantity, $actorId, $type, $unitCost, $refType, $refId, $notes) {
            // Lock the row to prevent race conditions
            $fresh = InventoryItem::lockForUpdate()->findOrFail($item->id);

            $before = (float) $fresh->current_stock;
            $after  = $before + $quantity;

            $fresh->update([
                'current_stock' => $after,
                'unit_cost'     => $unitCost ?? $fresh->unit_cost,
                'updated_by'    => $actorId,
            ]);

            $item->refresh();

            return $fresh->transactions()->create([
                'type'            => $type,
                'quantity'        => $quantity,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'unit_cost'       => $unitCost ?? $fresh->unit_cost,
                'total_cost'      => $unitCost ? $quantity * $unitCost : null,
                'reference_type'  => $refType,
                'reference_id'    => $refId,
                'notes'           => $notes,
                'performed_by'    => $actorId,
                'created_by'      => $actorId,
                'updated_by'      => $actorId,
            ]);
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
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('الكمية يجب أن تكون موجبة');
        }

        return DB::transaction(function () use ($item, $quantity, $actorId, $type, $refType, $refId, $notes) {
            $fresh = InventoryItem::lockForUpdate()->findOrFail($item->id);

            if ((float) $fresh->current_stock < $quantity) {
                throw new \RuntimeException(
                    "المخزون غير كافٍ. المتاح: {$fresh->current_stock} {$fresh->unit}"
                );
            }

            $before = (float) $fresh->current_stock;
            $after  = $before - $quantity;

            $fresh->update([
                'current_stock' => $after,
                'updated_by'    => $actorId,
            ]);
            
            $item->refresh();

            return $fresh->transactions()->create([
                'type'            => $type,
                'quantity'        => -$quantity,   // negative = out
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'unit_cost'       => $fresh->unit_cost,
                'total_cost'      => (float) $fresh->unit_cost * $quantity,
                'reference_type'  => $refType,
                'reference_id'    => $refId,
                'notes'           => $notes,
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
}
