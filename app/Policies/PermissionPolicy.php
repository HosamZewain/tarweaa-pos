<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class PermissionPolicy
{
    use HasFilamentPermissions;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->hasPermission('permissions.viewAny');
    }

    public function view(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('permissions.view');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->hasPermission('permissions.create');
    }

    public function update(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('permissions.update');
    }

    public function delete(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('permissions.delete');
    }

    public function restore(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('permissions.restore');
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->isAdmin() && $user->hasPermission('permissions.forceDelete');
    }
}
