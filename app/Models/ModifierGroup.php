<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroup extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'name',
        'selection_type',
        'is_required',
        'min_selections',
        'max_selections',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_required'    => 'boolean',
        'is_active'      => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'sort_order'     => 'integer',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function modifiers(): HasMany
    {
        return $this->hasMany(MenuItemModifier::class)->orderBy('sort_order');
    }

    public function availableModifiers(): HasMany
    {
        return $this->hasMany(MenuItemModifier::class)
                    ->where('is_available', true)
                    ->orderBy('sort_order');
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_modifier_groups')
                    ->withPivot('sort_order');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function isSingleSelect(): bool
    {
        return $this->selection_type === 'single';
    }

    public function isMultiSelect(): bool
    {
        return $this->selection_type === 'multiple';
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
