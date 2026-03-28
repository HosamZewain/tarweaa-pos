<?php

namespace App\Models;

use App\Enums\OrderSettlementType;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderSettlement extends Model
{
    use HasAuditFields;

    protected $appends = ['settlement_type_label'];

    protected $fillable = [
        'order_id',
        'settlement_type',
        'beneficiary_user_id',
        'charge_account_user_id',
        'commercial_total_amount',
        'covered_amount',
        'remaining_payable_amount',
        'notes',
    ];

    protected $casts = [
        'settlement_type' => OrderSettlementType::class,
        'commercial_total_amount' => 'decimal:2',
        'covered_amount' => 'decimal:2',
        'remaining_payable_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function beneficiaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function chargeAccountUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charge_account_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderSettlementLine::class);
    }

    public function eligibleItemsTotal(): float
    {
        $this->loadMissing('lines');

        return round((float) $this->lines->sum('eligible_amount'), 2);
    }

    public function getSettlementTypeLabelAttribute(): string
    {
        return $this->settlement_type?->label() ?? '—';
    }
}
