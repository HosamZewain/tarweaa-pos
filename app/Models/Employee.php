<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends User
{
    protected $table = 'users';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('assigned_at', 'assigned_by');
    }

    public function scopeManageable(Builder $query): Builder
    {
        return $query->operationalEmployees();
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class, 'user_id');
    }

    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'user_id')->latest('effective_from');
    }

    public function employeePenalties(): HasMany
    {
        return $this->hasMany(EmployeePenalty::class, 'user_id')->latest('penalty_date');
    }
}
