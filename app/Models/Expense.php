<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'expense_number',
        'category_id',
        'shift_id',
        'drawer_session_id',
        'amount',
        'description',
        'payment_method',
        'receipt_number',
        'expense_date',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'expense_date' => 'date',
        'approved_at'  => 'datetime',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function drawerSession(): BelongsTo
    {
        return $this->belongsTo(CashierDrawerSession::class, 'drawer_session_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->approved_by !== null;
    }

    public function isCashExpense(): bool
    {
        return $this->payment_method === 'cash';
    }



    // ─────────────────────────────────────────
    // Auto-generate expense number
    // ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->expense_number)) {
                $date    = now()->format('Ymd');
                $lastSeq = static::whereDate('created_at', today())->lockForUpdate()->count();
                $expense->expense_number = sprintf('EXP-%s-%03d', $date, $lastSeq + 1);
            }
        });
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeForShift($query, int $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', 'cash');
    }

    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('expense_date', $date);
    }
}
