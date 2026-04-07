<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvance extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'user_id',
        'amount',
        'advance_date',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'advance_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function payrollAllocations(): HasMany
    {
        return $this->hasMany(EmployeeAdvancePayrollAllocation::class, 'employee_advance_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    protected static function booted(): void
    {
        static::creating(function (EmployeeAdvance $advance): void {
            if (auth()->check()) {
                $advance->created_by ??= auth()->id();
                $advance->updated_by ??= auth()->id();
            }
        });

        static::updating(function (EmployeeAdvance $advance): void {
            if (auth()->check()) {
                $advance->updated_by = auth()->id();
            }
        });
    }
}
