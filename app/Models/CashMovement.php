<?php

namespace App\Models;

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'drawer_session_id',
        'shift_id',
        'cashier_id',
        'type',
        'direction',
        'amount',
        'reference_type',
        'reference_id',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'type'      => CashMovementType::class,
        'direction' => CashMovementDirection::class,
        'amount'    => 'decimal:2',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function drawerSession(): BelongsTo
    {
        return $this->belongsTo(CashierDrawerSession::class, 'drawer_session_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeIn($query)
    {
        return $query->where('direction', CashMovementDirection::In);
    }

    public function scopeOut($query)
    {
        return $query->where('direction', CashMovementDirection::Out);
    }

    public function scopeForDrawer($query, int $sessionId)
    {
        return $query->where('drawer_session_id', $sessionId);
    }

    public function scopeForShift($query, int $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }
}
