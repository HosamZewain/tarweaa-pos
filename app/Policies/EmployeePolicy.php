<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class EmployeePolicy
{
    use HasFilamentPermissions;

    protected string $permissionPrefix = 'employees';

    public function view(User $user, $model): bool
    {
        return $model instanceof Employee
            && $model->isManageableEmployee()
            && $user->hasPermission($this->permissionPrefix . '.view');
    }

    public function update(User $user, $model): bool
    {
        return $model instanceof Employee
            && $model->isManageableEmployee()
            && $user->hasPermission($this->permissionPrefix . '.update');
    }

    public function delete(User $user, $model): bool
    {
        return false;
    }

    public function restore(User $user, $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, $model): bool
    {
        return false;
    }
}
