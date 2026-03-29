<?php

namespace App\Models;

use App\Support\BusinessTime;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'status',
        'invoice_number',
        'invoice_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'paid_amount',
        'payment_status',
        'payment_method',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total'           => 'decimal:2',
        'paid_amount'     => 'decimal:2',
        'invoice_date'    => 'date',
        'received_at'     => 'datetime',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function remainingBalance(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_amount);
    }

    public function isFullyReceived(): bool
    {
        return $this->items->every(function (PurchaseItem $item) {
            return (float) $item->quantity_received >= (float) $item->quantity_ordered;
        });
    }

    public function recalculate(): void
    {
        $subtotal = $this->items()->sum('total');
        $total    = (float) $subtotal - (float) $this->discount_amount + (float) $this->tax_amount;

        $this->update([
            'subtotal' => $subtotal,
            'total'    => $total,
        ]);
    }

    // ─────────────────────────────────────────
    // Auto-generate purchase number
    // ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase) {
            if (empty($purchase->purchase_number)) {
                $date = BusinessTime::localDateKey();
                [$start, $end] = BusinessTime::utcRangeForLocalDate(BusinessTime::today());
                $lastSeq = static::whereBetween('created_at', [$start, $end])->lockForUpdate()->count();
                $purchase->purchase_number = sprintf('PO-%s-%03d', $date, $lastSeq + 1);
            }
        });
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', 'paid');
    }
}
