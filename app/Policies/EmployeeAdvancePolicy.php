<?php

namespace App\Policies;

use App\Models\EmployeeAdvance;
use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class EmployeeAdvancePolicy
{
    use HasFilamentPermissions;

    protected string $permissionPrefix = 'employee_advances';

    public function update(User $user, $model): bool
    {
        return $model instanceof EmployeeAdvance
            && !$model->isCancelled()
            && $user->hasPermission('employee_advances.update');
    }

    public function delete(User $user, $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, EmployeeAdvance $advance): bool
    {
        return !$advance->isCancelled() && $user->hasPermission('employee_advances.cancel');
    }
}
