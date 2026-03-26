<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItemModifier extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'modifier_group_id',
        'name',
        'price',
        'cost_price',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'cost_price'   => 'decimal:2',
        'is_available' => 'boolean',
        'sort_order'   => 'integer',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function orderItemModifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class, 'menu_item_modifier_id');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
}
