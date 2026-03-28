<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class RolePolicy
{
    use HasFilamentPermissions;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->hasPermission('roles.viewAny');
    }

    public function view(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->hasPermission('roles.create');
    }

    public function update(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('roles.update');
    }

    public function delete(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('roles.delete');
    }

    public function restore(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('roles.restore');
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('roles.forceDelete');
    }
}
