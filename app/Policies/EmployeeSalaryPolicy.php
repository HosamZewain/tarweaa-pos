<?php

namespace App\Policies;

use App\Policies\Traits\HasFilamentPermissions;

class EmployeeSalaryPolicy
{
    use HasFilamentPermissions;

    protected string $permissionPrefix = 'employee_salaries';
}
