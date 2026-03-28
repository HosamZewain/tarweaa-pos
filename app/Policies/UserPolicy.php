<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class UserPolicy
{
    use HasFilamentPermissions;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->hasPermission('users.viewAny');
    }

    public function view(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->hasPermission('users.create');
    }

    public function update(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('users.update');
    }

    public function delete(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('users.delete');
    }

    public function restore(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('users.restore');
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('users.forceDelete');
    }
}
