<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Guard table — enforces ONE open drawer session per cashier at the DB level.
 * cashier_id is the PRIMARY KEY. Any attempt to insert a second row
 * for the same cashier will fail with a duplicate-key exception.
 *
 * Rules:
 *  - INSERT when drawer opens
 *  - DELETE when drawer closes
 *  - Never update
 */
class CashierActiveSession extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'cashier_id';

    public $incrementing = false;

    protected $fillable = [
        'cashier_id',
        'drawer_session_id',
        'pos_device_id',
        'shift_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function drawerSession(): BelongsTo
    {
        return $this->belongsTo(CashierDrawerSession::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    // ─────────────────────────────────────────
    // Booted
    // ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (CashierActiveSession $model) {
            $model->created_at = now();
        });
    }
}
