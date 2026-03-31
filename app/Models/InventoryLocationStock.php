<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLocationStock extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'inventory_item_id',
        'inventory_location_id',
        'current_stock',
        'minimum_stock',
        'maximum_stock',
        'unit_cost',
    ];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'maximum_stock' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }
}
