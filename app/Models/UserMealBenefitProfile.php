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
}
