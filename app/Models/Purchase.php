<?php

namespace App\Models;

use App\Services\InventoryLocationService;
use App\Support\BusinessTime;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Purchase extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'destination_location_id',
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

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
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

    public function pendingItemsCount(): int
    {
        return $this->items()->get()->filter(fn (PurchaseItem $item) => $item->pendingQuantity() > 0)->count();
    }

    public function receiveAllPendingItems(): int
    {
        return DB::transaction(function (): int {
            $this->loadMissing('items.inventoryItem');

            $receivedLines = 0;

            foreach ($this->items as $item) {
                $pending = $item->pendingQuantity();

                if ($pending <= 0) {
                    continue;
                }

                $item->receive($pending);
                $receivedLines++;
            }

            return $receivedLines;
        });
    }

    // ─────────────────────────────────────────
    // Auto-generate purchase number
    // ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase) {
            $purchase->subtotal ??= 0;
            $purchase->tax_amount ??= 0;
            $purchase->discount_amount ??= 0;
            $purchase->paid_amount ??= 0;
            $purchase->total ??= max(0, (float) $purchase->subtotal - (float) $purchase->discount_amount + (float) $purchase->tax_amount);

            if (empty($purchase->destination_location_id)) {
                $purchase->destination_location_id = app(InventoryLocationService::class)
                    ->defaultPurchaseDestination()?->id;
            }

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
