<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class Role extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
        'show_in_employee_resource',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_employee_resource' => 'boolean',
    ];

    protected static ?array $cachedEmployeeResourceRoleNames = null;

    protected static function booted(): void
    {
        static::saved(fn () => static::flushEmployeeResourceRoleNamesCache());
        static::deleted(fn () => static::flushEmployeeResourceRoleNamesCache());
    }

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
                    ->withPivot('assigned_at', 'assigned_by');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function givePermissionTo(string|array $permissions): void
    {
        $ids = Permission::whereIn('name', (array) $permissions)->pluck('id');
        $this->permissions()->syncWithoutDetaching($ids);
    }

    public function revokePermissionTo(string|array $permissions): void
    {
        $ids = Permission::whereIn('name', (array) $permissions)->pluck('id');
        $this->permissions()->detach($ids);
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeShownInEmployeeResource($query)
    {
        return $query->where('show_in_employee_resource', true);
    }

    public static function employeeResourceRoleNames(): array
    {
        if (static::$cachedEmployeeResourceRoleNames !== null) {
            return static::$cachedEmployeeResourceRoleNames;
        }

        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'show_in_employee_resource')) {
            return static::$cachedEmployeeResourceRoleNames = ['employee', 'cashier', 'kitchen', 'counter'];
        }

        $names = static::query()
            ->shownInEmployeeResource()
            ->orderBy('display_name')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return static::$cachedEmployeeResourceRoleNames = !empty($names)
            ? $names
            : ['employee', 'cashier', 'kitchen', 'counter'];
    }

    public static function flushEmployeeResourceRoleNamesCache(): void
    {
        static::$cachedEmployeeResourceRoleNames = null;
    }
}
