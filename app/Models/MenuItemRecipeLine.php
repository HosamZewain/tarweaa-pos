<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemRecipeLine extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'menu_item_id',
        'inventory_item_id',
        'quantity',
        'unit',
        'unit_conversion_rate',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_conversion_rate' => 'decimal:6',
        'sort_order' => 'integer',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function baseQuantity(): float
    {
        return round((float) $this->quantity * (float) $this->unit_conversion_rate, 6);
    }

    public function lineCost(): float
    {
        return round($this->baseQuantity() * (float) ($this->inventoryItem?->unit_cost ?? 0), 2);
    }
}
