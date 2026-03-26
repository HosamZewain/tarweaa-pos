<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'sku',
        'image',
        'type',
        'base_price',
        'cost_price',
        'preparation_time',
        'track_inventory',
        'is_available',
        'is_active',
        'sort_order',
    ];

    protected $appends = ['price'];

    protected $casts = [
        'base_price'       => 'decimal:2',
        'cost_price'       => 'decimal:2',
        'track_inventory'  => 'boolean',
        'is_available'     => 'boolean',
        'is_active'        => 'boolean',
        'preparation_time' => 'integer',
        'sort_order'       => 'integer',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class)->orderBy('sort_order');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_item_modifier_groups')
                    ->withPivot('sort_order')
                    ->orderBy('menu_item_modifier_groups.sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function isVariable(): bool
    {
        return $this->type === 'variable';
    }

    public function isSimple(): bool
    {
        return $this->type === 'simple';
    }

    /**
     * Returns the effective selling price.
     * For variable items, call variant->price directly.
     */
    public function effectivePrice(): string
    {
        return $this->base_price;
    }

    public function getPriceAttribute()
    {
        return $this->base_price;
    }

    /**
     * Toggles real-time availability without deactivating the item.
     */
    public function toggleAvailability(): void
    {
        $this->update(['is_available' => !$this->is_available]);
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)->where('is_available', true);
    }

    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
