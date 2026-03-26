<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'group',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    /** Returns all permissions keyed by group for UI rendering. */
    public static function groupedForDisplay(): \Illuminate\Support\Collection
    {
        return static::orderBy('group')->orderBy('display_name')->get()
            ->groupBy('group');
    }
}
