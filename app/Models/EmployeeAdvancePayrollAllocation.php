<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvancePayrollAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_line_id',
        'employee_advance_id',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    public function payrollRunLine(): BelongsTo
    {
        return $this->belongsTo(PayrollRunLine::class);
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }
}
