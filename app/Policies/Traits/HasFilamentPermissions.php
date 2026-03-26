<?php

namespace App\Policies\Traits;

use App\Models\User;
use Illuminate\Support\Str;

trait HasFilamentPermissions
{
    /**
     * Get the base permission name for the model (e.g., 'orders', 'users').
     * If $permissionPrefix is set on the policy, it will use that.
     */
    protected function getPermissionPrefix(): string
    {
        if (isset($this->permissionPrefix)) {
            return $this->permissionPrefix;
        }

        $className = class_basename(str_replace('Policy', '', static::class));
        return Str::plural(Str::snake($className));
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->getPermissionPrefix() . '.viewAny');
    }

    public function view(User $user, $model): bool
    {
        return $user->hasPermission($this->getPermissionPrefix() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->getPermissionPrefix() . '.create');
    }

    public function update(User $user, $model): bool
    {
        return $user->hasPermission($this->getPermissionPrefix() . '.update');
    }

    public function delete(User $user, $model): bool
    {
        return $user->hasPermission($this->getPermissionPrefix() . '.delete');
    }

    public function restore(User $user, $model): bool
    {
        return $user->hasPermission($this->getPermissionPrefix() . '.restore');
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->hasPermission($this->getPermissionPrefix() . '.forceDelete');
    }
}
