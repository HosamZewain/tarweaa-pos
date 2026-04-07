<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRunLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'user_id',
        'employee_name',
        'job_title',
        'salary_effective_from',
        'salary_effective_to',
        'penalties_count',
        'advances_count',
        'penalties_snapshot',
        'advances_snapshot',
        'base_salary',
        'penalties_total',
        'advances_total',
        'net_salary',
    ];

    protected $casts = [
        'salary_effective_from' => 'date',
        'salary_effective_to' => 'date',
        'penalties_snapshot' => 'array',
        'advances_snapshot' => 'array',
        'base_salary' => 'decimal:2',
        'penalties_total' => 'decimal:2',
        'advances_total' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function advanceAllocations(): HasMany
    {
        return $this->hasMany(EmployeeAdvancePayrollAllocation::class)->orderBy('id');
    }
}
