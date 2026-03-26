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
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'payment_method' => PaymentMethod::class,
        'amount'         => 'decimal:2',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function isCash(): bool
    {
        return $this->payment_method === PaymentMethod::Cash;
    }
}
