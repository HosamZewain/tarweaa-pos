<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRecipe extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'prepared_item_id',
        'name',
        'output_quantity',
        'output_unit',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function preparedItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'prepared_item_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionRecipeLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class);
    }
}
