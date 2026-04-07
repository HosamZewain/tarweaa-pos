<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'month_key',
        'period_start',
        'period_end',
        'status',
        'employees_count',
        'total_base_salary',
        'total_penalties',
        'total_advances',
        'total_net_salary',
        'notes',
        'generated_at',
        'generated_by',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'month_key' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_base_salary' => 'decimal:2',
        'total_penalties' => 'decimal:2',
        'total_advances' => 'decimal:2',
        'total_net_salary' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class)->orderBy('employee_name');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
