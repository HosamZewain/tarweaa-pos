<?php

namespace App\Policies;

use App\Policies\Traits\HasFilamentPermissions;

class EmployeePenaltyPolicy
{
    use HasFilamentPermissions;

    protected string $permissionPrefix = 'employee_penalties';
}
