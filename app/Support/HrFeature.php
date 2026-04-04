<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class HrFeature
{
    protected static ?bool $hasSalaryTables = null;
    protected static ?bool $hasPenaltyTables = null;

    public static function hasSalaryTables(): bool
    {
        if (static::$hasSalaryTables !== null) {
            return static::$hasSalaryTables;
        }

        return static::$hasSalaryTables =
            Schema::hasTable('employee_salaries');
    }

    public static function hasPenaltyTables(): bool
    {
        if (static::$hasPenaltyTables !== null) {
            return static::$hasPenaltyTables;
        }

        return static::$hasPenaltyTables =
            Schema::hasTable('employee_penalties');
    }
}
