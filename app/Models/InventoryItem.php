<?php

namespace App\Models;


use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name',
        'sku',
        'category',
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

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    // ─────────────────────────────────────────
    // Stock Status Helpers
    // ─────────────────────────────────────────

    public function isLowStock(): bool
    {
        return (float) $this->current_stock <= (float) $this->minimum_stock;
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
}
