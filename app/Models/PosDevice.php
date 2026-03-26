<?php

namespace App\Models;

use App\Enums\DrawerSessionStatus;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosDevice extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'name',
        'identifier',
        'location',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function drawerSessions(): HasMany
    {
        return $this->hasMany(CashierDrawerSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function pingAlive(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    public function isOnline(int $thresholdMinutes = 5): bool
    {
        return $this->last_seen_at
            && $this->last_seen_at->diffInMinutes(now()) <= $thresholdMinutes;
    }

    public function hasOpenDrawerSession(): bool
    {
        return $this->drawerSessions()
                    ->where('status', DrawerSessionStatus::Open)
                    ->exists();
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
