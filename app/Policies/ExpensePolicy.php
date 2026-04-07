<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class ExpensePolicy
{
    use HasFilamentPermissions;

    public function update(User $user, $model): bool
    {
        return $model instanceof Expense
            && !$model->isApproved()
            && $user->hasPermission('expenses.update');
    }

    public function delete(User $user, $model): bool
    {
        return $model instanceof Expense
            && !$model->isApproved()
            && $user->hasPermission('expenses.delete');
    }
}
