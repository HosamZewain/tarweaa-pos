<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferItem extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'inventory_transfer_id',
        'inventory_item_id',
        'unit',
        'quantity_sent',
        'quantity_received',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'quantity_sent' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryTransferItem $item): void {
            if (auth()->check()) {
                $item->created_by ??= auth()->id();
                $item->updated_by ??= auth()->id();
            }
        });

        static::updating(function (InventoryTransferItem $item): void {
            if (auth()->check()) {
                $item->updated_by = auth()->id();
            }
        });
    }

    public function inventoryTransfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
