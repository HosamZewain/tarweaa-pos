<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecipeLine extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'production_recipe_id',
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

    public function productionRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function baseQuantity(): float
    {
        return round((float) $this->quantity * (float) $this->unit_conversion_rate, 6);
    }
}
