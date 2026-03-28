<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EmployeeManagementService
{
    public function assignableRoleNames(): array
    {
        return User::assignableEmployeeRoleNames();
    }

    public function assignableRoleOptions(): array
    {
        $labels = [
            'employee' => 'موظف بدون صلاحيات تشغيلية',
            'cashier' => 'كاشير',
            'kitchen' => 'مطبخ',
            'counter' => 'كاونتر / تسليم',
        ];

        return collect($this->assignableRoleNames())
            ->mapWithKeys(fn (string $role) => [$role => $labels[$role] ?? $role])
            ->all();
    }

    public function assignableRoles(): Collection
    {
        $ordered = $this->assignableRoleNames();

        return Role::query()
            ->whereIn('name', $ordered)
            ->get()
            ->sortBy(fn (Role $role) => array_search($role->name, $ordered, true))
            ->values();
    }

    public function primaryAssignableRoleName(User $user): ?string
    {
        return $user->roles()
            ->whereIn('name', $this->assignableRoleNames())
            ->orderBy('display_name')
            ->value('name');
    }

    public function syncOperationalRole(User $user, ?string $roleName): void
    {
        if (!$user->isManageableEmployee()) {
            throw new InvalidArgumentException('لا يمكن إدارة حسابات الإدارة من شاشة الموظفين.');
        }

        $allowedRoles = $this->assignableRoles()->keyBy('name');

        if (!$roleName || !$allowedRoles->has($roleName)) {
            throw new InvalidArgumentException('الدور التشغيلي المحدد غير مسموح به.');
        }

        $currentRoleIds = $user->roles()
            ->whereIn('name', $this->assignableRoleNames())
            ->pluck('roles.id')
            ->all();

        if (!empty($currentRoleIds)) {
            $user->roles()->detach($currentRoleIds);
        }

        $user->roles()->syncWithoutDetaching([
            $allowedRoles[$roleName]->id => [
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ],
        ]);

        $user->forgetAuthorizationCache();
    }
}
