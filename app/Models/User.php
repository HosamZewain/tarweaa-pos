<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Enums\DrawerSessionStatus;
use App\Support\SystemPermissions;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasAuditFields;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && (
                $this->hasRole(['admin', 'manager', 'owner'])
                || $this->hasAnyPermission(static::adminPanelAccessPermissions())
            );
    }

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'pin',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'email_verified_at'  => 'datetime',
        'last_login_at'      => 'datetime',
        'password'           => 'hashed',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot('assigned_at', 'assigned_by');
    }

    public function drawerSessions(): HasMany
    {
        return $this->hasMany(CashierDrawerSession::class, 'cashier_id');
    }

    public function activeDrawerSession(): HasOne
    {
        return $this->hasOne(CashierDrawerSession::class, 'cashier_id')
                    ->where('status', DrawerSessionStatus::Open);
    }

    public function activeGuardSession(): HasOne
    {
        return $this->hasOne(CashierActiveSession::class, 'cashier_id');
    }

    /** Shifts this user has opened as a manager/supervisor. */
    public function openedShifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'opened_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'cashier_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cashier_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function appliedDiscountLogs(): HasMany
    {
        return $this->hasMany(DiscountLog::class, 'applied_by');
    }

    public function requestedDiscountLogs(): HasMany
    {
        return $this->hasMany(DiscountLog::class, 'requested_by');
    }

    public function mealBenefitProfile(): HasOne
    {
        return $this->hasOne(UserMealBenefitProfile::class);
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function mealBenefitLedgerEntries(): HasMany
    {
        return $this->hasMany(MealBenefitLedgerEntry::class);
    }

    public function settlementBeneficiaryOrders(): HasMany
    {
        return $this->hasMany(OrderSettlement::class, 'beneficiary_user_id');
    }

    public function chargedOrders(): HasMany
    {
        return $this->hasMany(OrderSettlement::class, 'charge_account_user_id');
    }

    public function settlementLines(): HasMany
    {
        return $this->hasMany(OrderSettlementLine::class);
    }

    // ─────────────────────────────────────────
    // RBAC Helpers
    // ─────────────────────────────────────────

    /**
     * Cached roles collection for the current request lifecycle.
     */
    protected ?Collection $cachedRoles = null;

    /**
     * Cached permission names for the current request lifecycle.
     */
    protected ?Collection $cachedPermissions = null;

    public function getRoles(): Collection
    {
        if ($this->cachedRoles === null) {
            $this->cachedRoles = $this->roles()->with('permissions')->get();
        }

        return $this->cachedRoles;
    }

    public function hasRole(string|array $role): bool
    {
        $roles = is_array($role) ? $role : [$role];

        return $this->getRoles()->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = $this->getRoles()
                ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                ->unique();
        }

        return $this->cachedPermissions->contains($permission);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return collect($permissions)->contains(fn ($p) => $this->hasPermission($p));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isCashier(): bool
    {
        return $this->hasRole('cashier');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public static function privilegedRoleNames(): array
    {
        return ['admin', 'manager', 'owner'];
    }

    public static function assignableEmployeeRoleNames(): array
    {
        return Role::employeeResourceRoleNames();
    }

    public function hasOperationalEmployeeRole(): bool
    {
        return $this->hasRole(static::assignableEmployeeRoleNames());
    }

    public function isManageableEmployee(): bool
    {
        return !$this->hasRole(static::privilegedRoleNames());
    }

    public function forgetAuthorizationCache(): void
    {
        $this->cachedRoles = null;
        $this->cachedPermissions = null;
        $this->unsetRelation('roles');
    }

    public function markSignedIn(): void
    {
        $this->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();
    }

    public static function activePinConflictExists(?string $pin, ?int $ignoreUserId = null): bool
    {
        if (blank($pin)) {
            return false;
        }

        return static::query()
            ->where('pin', $pin)
            ->where('is_active', true)
            ->when($ignoreUserId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreUserId))
            ->exists();
    }

    public function canAccessPosSurface(): bool
    {
        return $this->is_active && $this->hasRole(['admin', 'manager', 'cashier']);
    }

    public function canAccessKitchenSurface(): bool
    {
        return $this->is_active && $this->hasPermission('view_kitchen');
    }

    public function canAccessCounterSurface(): bool
    {
        return $this->is_active && $this->hasPermission('view_counter_screen');
    }

    public function canApproveDiscounts(): bool
    {
        return $this->is_active && $this->hasRole(['admin', 'manager']);
    }

    public function canViewDashboardAnalytics(): bool
    {
        return $this->is_active && $this->hasPermission('dashboard.analytics.view');
    }

    public function canViewLiveSessionFinancialStats(): bool
    {
        return $this->is_active && !$this->isCashier();
    }

    public function mustDeclareCashBeforeSeeingSessionFinancialStats(): bool
    {
        return !$this->canViewLiveSessionFinancialStats();
    }

    public function canCloseDrawerWithVariance(): bool
    {
        return $this->hasRole(['admin', 'manager']);
    }

    /**
     * Back-office permissions that justify access to the admin panel itself.
     *
     * Operational-only permissions like kitchen/counter/POS actions are
     * intentionally excluded so those users do not gain /admin access.
     */
    public static function adminPanelAccessPermissions(): array
    {
        $resourcePermissions = collect(SystemPermissions::resourcePermissions())
            ->pluck('name');

        $pagePermissions = collect(SystemPermissions::pagePermissions())
            ->pluck('name');

        $adminActionPermissions = collect([
            'shifts.open',
            'shifts.close',
            'drawers.open',
            'drawers.close',
            'drawers.cash_in',
            'drawers.cash_out',
            'orders.cancel',
            'orders.apply_special_settlement',
            'expenses.approve',
            'inventory_items.adjust_stock',
            'inventory_items.add_stock',
            'reports.meal_benefits.view',
        ]);

        return $resourcePermissions
            ->merge($pagePermissions)
            ->merge($adminActionPermissions)
            ->unique()
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────
    // Business Logic Helpers
    // ─────────────────────────────────────────

    /**
     * Returns the currently active open shift, or null if none exists.
     */
    public function getActiveShift(): ?Shift
    {
        $session = $this->activeDrawerSession()->with('shift')->first();

        return $session?->shift?->status === ShiftStatus::Open
            ? $session->shift
            : null;
    }

    /**
     * Returns true if the user has all prerequisites to create an order:
     *  1. Account is active
     *  2. An open shift exists
     *  3. An open drawer session exists for this cashier
     */
    public function canCreateOrder(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $drawerSession = $this->activeDrawerSession()->with('shift')->first();

        if (!$drawerSession) {
            return false;
        }

        return $drawerSession->shift?->status === ShiftStatus::Open;
    }

    /**
     * Returns a human-readable reason if canCreateOrder() returns false.
     */
    public function getOrderBlockReason(): ?string
    {
        if (!$this->is_active) {
            return 'الحساب غير نشط';
        }

        $drawerSession = $this->activeDrawerSession()->with('shift')->first();

        if (!$drawerSession) {
            return 'لا توجد جلسة درج مفتوحة لهذا الكاشير';
        }

        if ($drawerSession->shift?->status !== ShiftStatus::Open) {
            return 'لا يوجد وردية مفتوحة';
        }

        return null;
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('name', $role));
    }

    public function scopeOperationalEmployees(Builder $query): Builder
    {
        return $query->whereHas('roles', function (Builder $roleQuery): void {
            $roleQuery->whereIn('name', static::assignableEmployeeRoleNames());
        });
    }

    public function scopeWithoutOperationalEmployeeRoles(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', function (Builder $roleQuery): void {
            $roleQuery->whereIn('name', static::assignableEmployeeRoleNames());
        });
    }
}
