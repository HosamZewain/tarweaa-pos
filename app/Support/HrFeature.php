<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class HrFeature
{
    public static function hasSalaryTables(): bool
    {
        return Schema::hasTable('employee_salaries');
    }

    public static function hasPenaltyTables(): bool
    {
        return Schema::hasTable('employee_penalties');
    }

    public static function hasAdvanceTables(): bool
    {
        return Schema::hasTable('employee_advances');
    }

    public static function hasPayrollTables(): bool
    {
        return Schema::hasTable('payroll_runs')
            && Schema::hasTable('payroll_run_lines')
            && Schema::hasTable('employee_advance_payroll_allocations');
    }
}
