<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use HasAuditFields;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'terminal_id',
        'reference_number',
        'fee_amount',
        'net_settlement_amount',
        'notes',
    ];

    protected $casts = [
        'payment_method' => PaymentMethod::class,
        'amount'         => 'decimal:2',
        'fee_amount'     => 'decimal:2',
        'net_settlement_amount' => 'decimal:2',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PaymentTerminal::class, 'terminal_id');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function isCash(): bool
    {
        return $this->payment_method === PaymentMethod::Cash;
    }

    public function isCard(): bool
    {
        return $this->payment_method === PaymentMethod::Card;
    }
}
