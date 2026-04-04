<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBatchLine extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'production_batch_id',
        'production_recipe_line_id',
        'inventory_item_id',
        'inventory_transaction_id',
        'planned_quantity',
        'actual_quantity',
        'base_quantity',
        'unit',
        'unit_conversion_rate',
        'unit_cost',
        'total_cost',
        'sort_order',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'base_quantity' => 'decimal:6',
        'unit_conversion_rate' => 'decimal:6',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }

    public function productionRecipeLine(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipeLine::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class);
    }
}
