<?php

namespace App\Models;

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\DrawerSessionStatus;
use App\Support\BusinessTime;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class CashierDrawerSession extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'session_number',
        'cashier_id',
        'shift_id',
        'pos_device_id',
        'opened_by',
        'closed_by',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'cash_difference',
        'status',
        'started_at',
        'ended_at',
        'notes',
    ];

    protected $casts = [
        'status'           => DrawerSessionStatus::class,
        'opening_balance'  => 'decimal:2',
        'closing_balance'  => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'cash_difference'  => 'decimal:2',
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'drawer_session_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'drawer_session_id');
    }

    public function reportableOrders(): HasMany
    {
        return $this->orders()->reportable();
    }

    public function cancelledOrders(): HasMany
    {
        return $this->orders()->cancelled();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'drawer_session_id');
    }

    // ─────────────────────────────────────────
    // Status Helpers
    // ─────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === DrawerSessionStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === DrawerSessionStatus::Closed;
    }

    // ─────────────────────────────────────────
    // Financial Calculations (read-only)
    // ─────────────────────────────────────────

    public function totalCashIn(): float
    {
        return (float) $this->cashMovements()
                            ->where('direction', CashMovementDirection::In)
                            ->sum('amount');
    }

    public function totalCashOut(): float
    {
        return (float) $this->cashMovements()
                            ->where('direction', CashMovementDirection::Out)
                            ->sum('amount');
    }

    public function manualCashInTotal(): float
    {
        return round($this->movementAmountByType(CashMovementType::CashIn), 2);
    }

    public function manualCashOutTotal(): float
    {
        return round($this->movementAmountByType(CashMovementType::CashOut), 2);
    }

    public function refundCashTotal(): float
    {
        return round($this->movementAmountByType(CashMovementType::Refund), 2);
    }

    public function reportableCashSalesTotal(): float
    {
        $orders = $this->reportableOrdersCollection();

        if ($orders->isNotEmpty()) {
            $orders->loadMissing('payments', 'settlement');
        }

        return round(
            $orders->sum(fn (Order $order) => $order->reportableCashPaidAmount()),
            2,
        );
    }

    public function reportableNonCashSalesTotal(): float
    {
        $orders = $this->reportableOrdersCollection();

        if ($orders->isNotEmpty()) {
            $orders->loadMissing('payments', 'settlement');
        }

        return round(
            $orders->sum(fn (Order $order) => $order->reportableNonCashPaidAmount()),
            2,
        );
    }

    /**
     * Expected balance = all cash-in movements − all cash-out movements.
     */
    public function calculateExpectedBalance(): float
    {
        return round(
            (float) $this->opening_balance
            + $this->reportableCashSalesTotal()
            + $this->manualCashInTotal()
            - $this->manualCashOutTotal()
            - $this->refundCashTotal(),
            2,
        );
    }

    public function reportableOrdersCollection(): Collection
    {
        $orders = $this->relationLoaded('orders')
            ? $this->orders
            : $this->orders()->get();

        return $orders
            ->filter(fn (Order $order) => $order->countsTowardSalesStats())
            ->values();
    }

    public function revenueOrdersCollection(): Collection
    {
        $orders = $this->relationLoaded('orders')
            ? $this->orders
            : $this->orders()->get();

        return $orders
            ->filter(fn (Order $order) => $order->countsTowardRevenueStats())
            ->values();
    }

    public function cancelledOrdersCollection(): Collection
    {
        $orders = $this->relationLoaded('orders')
            ? $this->orders
            : $this->orders()->get();

        return $orders
            ->filter(fn (Order $order) => $order->isCancelled())
            ->values();
    }

    // ─────────────────────────────────────────
    // Cash Movement Helper (no auth() usage)
    // ─────────────────────────────────────────

    /**
     * Record a cash movement against this drawer session.
     * The caller (service layer) MUST provide the actor ID.
     */
    public function addMovement(
        CashMovementType $type,
        float            $amount,
        int              $performedBy,
        ?string          $referenceType = null,
        ?int             $referenceId   = null,
        ?string          $notes         = null,
    ): CashMovement {
        return $this->cashMovements()->create([
            'shift_id'       => $this->shift_id,
            'cashier_id'     => $this->cashier_id,
            'type'           => $type,
            'direction'      => $type->direction(),
            'amount'         => abs($amount),
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'notes'          => $notes,
            'performed_by'   => $performedBy,
            'created_by'     => $performedBy,
            'updated_by'     => $performedBy,
        ]);
    }

    private function movementAmountByType(CashMovementType $type): float
    {
        if ($this->relationLoaded('cashMovements')) {
            return (float) $this->cashMovements
                ->where('type', $type)
                ->sum('amount');
        }

        return (float) $this->cashMovements()
            ->where('type', $type->value)
            ->sum('amount');
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', DrawerSessionStatus::Open);
    }

    public function scopeForCashier($query, int $cashierId)
    {
        return $query->where('cashier_id', $cashierId);
    }

    public function scopeForShift($query, int $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    // ─────────────────────────────────────────
    // Auto-generate session number
    // ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (CashierDrawerSession $session) {
            if (empty($session->session_number)) {
                $session->session_number = static::generateSessionNumber();
            }
        });
    }

    public static function generateSessionNumber(): string
    {
        $date = BusinessTime::localDateKey();
        [$start, $end] = BusinessTime::utcRangeForLocalDate(BusinessTime::today());

        $lastSeq = static::whereBetween('created_at', [$start, $end])
            ->lockForUpdate()
            ->count();

        return sprintf('DRW-%s-%03d', $date, $lastSeq + 1);
    }
}
