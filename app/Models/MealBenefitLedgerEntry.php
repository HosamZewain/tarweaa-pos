<?php

namespace App\Models;

use App\Enums\MealBenefitLedgerEntryType;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealBenefitLedgerEntry extends Model
{
    use HasAuditFields;

    protected $fillable = [
        'user_id',
        'profile_id',
        'order_id',
        'order_settlement_line_id',
        'entry_type',
        'amount',
        'meals_count',
        'benefit_period_start',
        'benefit_period_end',
        'notes',
    ];

    protected $casts = [
        'entry_type' => MealBenefitLedgerEntryType::class,
        'amount' => 'decimal:2',
        'meals_count' => 'integer',
        'benefit_period_start' => 'date',
        'benefit_period_end' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(UserMealBenefitProfile::class, 'profile_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function settlementLine(): BelongsTo
    {
        return $this->belongsTo(OrderSettlementLine::class, 'order_settlement_line_id');
    }
}
