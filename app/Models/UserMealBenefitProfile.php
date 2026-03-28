<?php

namespace App\Models;

use App\Enums\UserMealBenefitFreeMealType;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserMealBenefitProfile extends Model
{
    use HasAuditFields;

    public const BENEFIT_MODE_NONE = 'none';
    public const BENEFIT_MODE_OWNER_CHARGE = 'owner_charge';
    public const BENEFIT_MODE_MONTHLY_ALLOWANCE = 'monthly_allowance';
    public const BENEFIT_MODE_FREE_MEAL = 'free_meal';

    protected $fillable = [
        'user_id',
        'is_active',
        'can_receive_owner_charge_orders',
        'monthly_allowance_enabled',
        'monthly_allowance_amount',
        'free_meal_enabled',
        'free_meal_type',
        'free_meal_monthly_count',
        'free_meal_monthly_amount',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_receive_owner_charge_orders' => 'boolean',
        'monthly_allowance_enabled' => 'boolean',
        'monthly_allowance_amount' => 'decimal:2',
        'free_meal_enabled' => 'boolean',
        'free_meal_type' => UserMealBenefitFreeMealType::class,
        'free_meal_monthly_count' => 'integer',
        'free_meal_monthly_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowedMenuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'user_meal_benefit_profile_menu_item', 'profile_id', 'menu_item_id')
            ->withTimestamps();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(MealBenefitLedgerEntry::class, 'profile_id');
    }

    public function orderSettlementLines(): HasMany
    {
        return $this->hasMany(OrderSettlementLine::class, 'profile_id');
    }

    public function benefitMode(): string
    {
        $enabledModes = collect([
            self::BENEFIT_MODE_OWNER_CHARGE => $this->can_receive_owner_charge_orders,
            self::BENEFIT_MODE_MONTHLY_ALLOWANCE => $this->monthly_allowance_enabled,
            self::BENEFIT_MODE_FREE_MEAL => $this->free_meal_enabled,
        ])->filter();

        if ($enabledModes->isEmpty()) {
            return self::BENEFIT_MODE_NONE;
        }

        if ($enabledModes->count() > 1) {
            return 'mixed';
        }

        return (string) $enabledModes->keys()->first();
    }

    public function benefitModeLabel(): string
    {
        return match ($this->benefitMode()) {
            self::BENEFIT_MODE_OWNER_CHARGE => 'تحميل مالك / إدارة',
            self::BENEFIT_MODE_MONTHLY_ALLOWANCE => 'بدل شهري للموظف',
            self::BENEFIT_MODE_FREE_MEAL => 'وجبة مجانية للموظف',
            'mixed' => 'أكثر من نوع مفعّل',
            default => 'بدون مزايا',
        };
    }

    public function freeMealLimitLabel(): string
    {
        if (!$this->free_meal_enabled || !$this->free_meal_type) {
            return '—';
        }

        return match ($this->free_meal_type) {
            UserMealBenefitFreeMealType::Count => sprintf('%s وجبة', number_format((int) ($this->free_meal_monthly_count ?? 0))),
            UserMealBenefitFreeMealType::Amount => sprintf('%s ج.م', number_format((float) ($this->free_meal_monthly_amount ?? 0), 2)),
        };
    }
}
