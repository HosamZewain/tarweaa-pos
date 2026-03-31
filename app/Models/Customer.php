<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'loyalty_points',
        'total_orders',
        'total_spent',
        'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'loyalty_points' => 'integer',
        'total_orders'  => 'integer',
        'total_spent'   => 'decimal:2',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function addLoyaltyPoints(int $points): void
    {
        $this->increment('loyalty_points', $points);
    }

    public function redeemLoyaltyPoints(int $points): void
    {
        if ($points > $this->loyalty_points) {
            throw new \RuntimeException('رصيد النقاط غير كافٍ');
        }

        $this->decrement('loyalty_points', $points);
    }

    /**
     * Increment the denormalized counters after a new order is completed.
     */
    public function recordOrder(float $amount): void
    {
        $this->increment('total_orders');
        $this->increment('total_spent', $amount);
    }

    public function reverseRecordedOrder(float $amount): void
    {
        $this->update([
            'total_orders' => max(0, (int) $this->total_orders - 1),
            'total_spent' => max(0, round((float) $this->total_spent - $amount, 2)),
        ]);
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearchByPhone($query, string $phone)
    {
        return $query->where('phone', 'like', "%{$phone}%");
    }
}
