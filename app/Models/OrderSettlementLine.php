<?php

namespace App\Models;

use App\Enums\OrderSettlementLineType;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderSettlementLine extends Model
{
    use HasAuditFields;

    protected $fillable = [
        'order_settlement_id',
        'order_id',
        'line_type',
        'user_id',
        'profile_id',
        'order_item_id',
        'menu_item_id',
        'eligible_amount',
        'covered_amount',
        'covered_quantity',
        'benefit_period_start',
        'benefit_period_end',
        'notes',
    ];

    protected $casts = [
        'line_type' => OrderSettlementLineType::class,
        'eligible_amount' => 'decimal:2',
        'covered_amount' => 'decimal:2',
        'covered_quantity' => 'integer',
        'benefit_period_start' => 'date',
        'benefit_period_end' => 'date',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(OrderSettlement::class, 'order_settlement_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(UserMealBenefitProfile::class, 'profile_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
