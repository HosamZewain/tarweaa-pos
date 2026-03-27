<?php

namespace App\Models;

use App\Enums\PaymentTerminalFeeType;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTerminal extends Model
{
    use HasAuditFields;

    protected $fillable = [
        'name',
        'bank_name',
        'code',
        'fee_type',
        'fee_percentage',
        'fee_fixed_amount',
        'is_active',
    ];

    protected $casts = [
        'fee_type' => PaymentTerminalFeeType::class,
        'fee_percentage' => 'decimal:4',
        'fee_fixed_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class, 'terminal_id');
    }
}
