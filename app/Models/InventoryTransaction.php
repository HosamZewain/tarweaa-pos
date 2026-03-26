<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger of all stock movements.
 * Records are INSERT-only — never update or delete.
 */
class InventoryTransaction extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'inventory_item_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'type'            => InventoryTransactionType::class,
        'quantity'        => 'decimal:3',
        'quantity_before' => 'decimal:3',
        'quantity_after'  => 'decimal:3',
        'unit_cost'       => 'decimal:2',
        'total_cost'      => 'decimal:2',
    ];

    // ─────────────────────────────────────────
    // Immutability Guard
    // ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException(
                'سجلات حركات المخزون غير قابلة للتعديل. أضف حركة تصحيح جديدة بدلاً من ذلك.'
            );
        });

        static::deleting(function () {
            throw new \RuntimeException(
                'سجلات حركات المخزون غير قابلة للحذف.'
            );
        });
    }

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function isStockIncrease(): bool
    {
        return (float) $this->quantity > 0;
    }

    public function isStockDecrease(): bool
    {
        return (float) $this->quantity < 0;
    }

    public function absoluteQuantity(): float
    {
        return abs((float) $this->quantity);
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('inventory_item_id', $itemId);
    }

    public function scopeByType($query, InventoryTransactionType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeIncrements($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeDecrements($query)
    {
        return $query->where('quantity', '<', 0);
    }

    public function scopeDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
    }
}
