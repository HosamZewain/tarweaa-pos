<?php

namespace App\Models;

use App\Enums\DrawerSessionStatus;
use App\Enums\ShiftStatus;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'shift_number',
        'status',
        'opened_by',
        'closed_by',
        'started_at',
        'ended_at',
        'notes',
        'expected_cash',
        'actual_cash',
        'cash_difference',
    ];

    protected $casts = [
        'status'          => ShiftStatus::class,
        'started_at'      => 'datetime',
        'ended_at'        => 'datetime',
        'expected_cash'   => 'decimal:2',
        'actual_cash'     => 'decimal:2',
        'cash_difference' => 'decimal:2',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function drawerSessions(): HasMany
    {
        return $this->hasMany(CashierDrawerSession::class);
    }

    public function openDrawerSessions(): HasMany
    {
        return $this->hasMany(CashierDrawerSession::class)
                    ->where('status', DrawerSessionStatus::Open);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // ─────────────────────────────────────────
    // Status Helpers
    // ─────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === ShiftStatus::Closed;
    }

    /**
     * A shift can only close if ALL drawer sessions are closed.
     * This is the business-rule guard — call before attempting to close.
     */
    public function canClose(): bool
    {
        return $this->isOpen() && $this->openDrawerSessions()->doesntExist();
    }

    /**
     * Returns array of cashier names who still have open drawer sessions.
     * Use for error messages when shift close is blocked.
     */
    public function openDrawerCashiers(): \Illuminate\Support\Collection
    {
        return $this->openDrawerSessions()
                    ->with('cashier:id,name')
                    ->get()
                    ->pluck('cashier.name');
    }

    // ─────────────────────────────────────────
    // Financial Aggregates
    // ─────────────────────────────────────────

    /**
     * Total revenue from paid/delivered orders in this shift.
     */
    public function totalRevenue(): string
    {
        return $this->orders()
                    ->whereIn('payment_status', ['paid'])
                    ->sum('total');
    }

    /**
     * Total number of completed orders.
     */
    public function totalOrders(): int
    {
        return $this->orders()
                    ->whereNotIn('status', ['cancelled'])
                    ->count();
    }

    /**
     * Calculate shift expected cash from all drawer sessions (drawer-based).
     * This is the single source of truth for shift-level cash reconciliation.
     */
    public function calculateExpectedCashFromDrawers(): float
    {
        $total = 0.0;

        $this->drawerSessions->each(function (CashierDrawerSession $session) use (&$total) {
            $total += $session->calculateExpectedBalance();
        });

        return round($total, 2);
    }

    // ─────────────────────────────────────────
    // Duration Helpers
    // ─────────────────────────────────────────

    public function durationInHours(): ?float
    {
        if (!$this->ended_at) {
            return null;
        }

        return round($this->started_at->diffInMinutes($this->ended_at) / 60, 2);
    }

    public function durationLabel(): string
    {
        if (!$this->ended_at) {
            $diff = $this->started_at->diff(Carbon::now());
        } else {
            $diff = $this->started_at->diff($this->ended_at);
        }

        return sprintf('%d س %d دق', $diff->h + ($diff->days * 24), $diff->i);
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', ShiftStatus::Open);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', ShiftStatus::Closed);
    }

    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('started_at', $date);
    }

    // ─────────────────────────────────────────
    // Auto-generate shift number
    // ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Shift $shift) {
            if (empty($shift->shift_number)) {
                $shift->shift_number = static::generateShiftNumber();
            }
        });
    }

    public static function generateShiftNumber(): string
    {
        $date    = now()->format('Ymd');
        $lastSeq = static::whereDate('created_at', today())
                         ->lockForUpdate()
                         ->count();

        return sprintf('SHF-%s-%03d', $date, $lastSeq + 1);
    }
}
