<?php

namespace App\Models;


use App\Enums\InventoryItemType;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name',
        'sku',
        'category',
        'item_type',
        'unit',
        'unit_cost',
        'current_stock',
        'minimum_stock',
        'maximum_stock',
        'default_supplier_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'item_type'             => InventoryItemType::class,
        'unit_cost'            => 'decimal:2',
        'current_stock'        => 'decimal:3',
        'minimum_stock'        => 'decimal:3',
        'maximum_stock'        => 'decimal:3',
        'is_active'            => 'boolean',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function locationStocks(): HasMany
    {
        return $this->hasMany(InventoryLocationStock::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    public function productionRecipe(): HasOne
    {
        return $this->hasOne(ProductionRecipe::class, 'prepared_item_id');
    }

    public function productionBatchOutputs(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'prepared_item_id');
    }

    public function productionBatchLines(): HasMany
    {
        return $this->hasMany(ProductionBatchLine::class);
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(MenuItemRecipeLine::class);
    }

    // ─────────────────────────────────────────
    // Stock Status Helpers
    // ─────────────────────────────────────────

    public function isLowStock(): bool
    {
        return (float) $this->current_stock <= (float) $this->minimum_stock;
    }

    public function isPreparedItem(): bool
    {
        return $this->item_type === InventoryItemType::PreparedItem;
    }

    public function isRawMaterial(): bool
    {
        return $this->item_type === InventoryItemType::RawMaterial || $this->item_type === null;
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->current_stock <= 0;
    }

    public function stockPercentage(): float
    {
        if (!$this->maximum_stock || (float) $this->maximum_stock === 0.0) {
            return 100;
        }

        return min(100, ((float) $this->current_stock / (float) $this->maximum_stock) * 100);
    }

    // ─────────────────────────────────────────
    // Stock Mutation Helpers
    // ─────────────────────────────────────────



    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_stock', '<=', 'minimum_stock');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('current_stock', '<=', 0);
    }

    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopePreparedItems($query)
    {
        return $query->where('item_type', InventoryItemType::PreparedItem->value);
    }

    public function scopeRawMaterials($query)
    {
        return $query->where('item_type', InventoryItemType::RawMaterial->value);
    }
}
