<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class EmployeePenaltyPolicy
{
    use HasFilamentPermissions;

    protected string $permissionPrefix = 'employee_penalties';
}
