<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'inventory_item_id',
        'unit',
        'unit_price',
        'quantity_ordered',
        'quantity_received',
        'total',
        'notes',
    ];

    protected $casts = [
        'unit_price'        => 'decimal:2',
        'quantity_ordered'  => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'total'             => 'decimal:2',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function pendingQuantity(): float
    {
        return max(0, (float) $this->quantity_ordered - (float) $this->quantity_received);
    }

    public function isFullyReceived(): bool
    {
        return $this->pendingQuantity() <= 0;
    }

    /**
     * Receive a quantity against this line item and update inventory.
     */
    public function receive(float $quantity): void
    {
        if ($quantity > $this->pendingQuantity()) {
            throw new \InvalidArgumentException(
                "الكمية المستلمة ({$quantity}) تتجاوز الكمية المتبقية ({$this->pendingQuantity()})"
            );
        }

        $this->increment('quantity_received', $quantity);

        // Update inventory stock
        app(InventoryService::class)->addStock(
            item:        $this->inventoryItem,
            quantity:    $quantity,
            actorId:     $this->purchase->updated_by ?? $this->purchase->created_by ?? 1,
            type:        InventoryTransactionType::Purchase,
            unitCost:    (float) $this->unit_price,
            refType:     'purchase',
            refId:       $this->purchase_id,
            notes:       "استلام مشتريات رقم {$this->purchase->purchase_number}",
        );
    }
}
