<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;
 
    protected $appends = ['type_label', 'status_label', 'source_label', 'counter_number', 'counter_lane'];

    protected $fillable = [
        'order_number',
        'type',
        'status',
        'source',
        'cashier_id',
        'shift_id',
        'drawer_session_id',
        'pos_device_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'delivery_fee',
        'total',
        'payment_status',
        'paid_amount',
        'change_amount',
        'refund_amount',
        'refund_reason',
        'refunded_by',
        'refunded_at',
        'external_order_id',
        'external_order_number',
        'notes',
        'scheduled_at',
        'confirmed_at',
        'ready_at',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'type'           => OrderType::class,
        'status'         => OrderStatus::class,
        'source'         => OrderSource::class,
        'payment_status' => PaymentStatus::class,
        'subtotal'       => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate'       => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'delivery_fee'   => 'decimal:2',
        'total'          => 'decimal:2',
        'paid_amount'    => 'decimal:2',
        'change_amount'  => 'decimal:2',
        'refund_amount'  => 'decimal:2',
        'scheduled_at'   => 'datetime',
        'confirmed_at'   => 'datetime',
        'ready_at'       => 'datetime',
        'dispatched_at'  => 'datetime',
        'delivered_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
        'refunded_at'    => 'datetime',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function drawerSession(): BelongsTo
    {
        return $this->belongsTo(CashierDrawerSession::class, 'drawer_session_id');
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)
                    ->where('status', '!=', OrderItemStatus::Cancelled);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(OrderSettlement::class);
    }

    public function settlementLines(): HasMany
    {
        return $this->hasMany(OrderSettlementLine::class);
    }

    public function discountLogs(): HasMany
    {
        return $this->hasMany(DiscountLog::class);
    }

    public function orderDiscountLogs(): HasMany
    {
        return $this->hasMany(DiscountLog::class)
            ->where('scope', 'order')
            ->latest('created_at');
    }

    public function latestOrderDiscountLog(): HasOne
    {
        return $this->hasOne(DiscountLog::class)
            ->where('scope', 'order')
            ->latestOfMany();
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ─────────────────────────────────────────
    // Status Helpers
    // ─────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::Cancelled;
    }

    public function isCancellable(): bool
    {
        return $this->status->isCancellable();
    }

    public function isDelivery(): bool
    {
        return $this->type === OrderType::Delivery;
    }

    public function isFromExternalSource(): bool
    {
        return $this->source->isExternal();
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_amount);
    }

    public function isFullyPaid(): bool
    {
        return $this->remainingAmount() <= 0;
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type->label();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getSourceLabelAttribute(): string
    {
        return $this->source->label();
    }

    public function getCounterNumberAttribute(): ?int
    {
        if (!$this->order_number || !preg_match('/(\d+)$/', $this->order_number, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public function getCounterLaneAttribute(): ?string
    {
        $number = $this->counter_number;

        if ($number === null) {
            return null;
        }

        return $number % 2 === 0 ? 'even' : 'odd';
    }

    // ─────────────────────────────────────────
    // Totals Recalculation
    // ─────────────────────────────────────────

    /**
     * Recalculates subtotal, discount, tax, and total from order items.
     * Call after adding/removing items or changing discount/tax.
     */
    public function recalculate(): void
    {
        $subtotal = (float) $this->activeItems()->sum('total');

        if ($this->discount_type === 'percentage') {
            $discountAmount = $subtotal * ((float) $this->discount_value / 100);
        } else {
            $discountAmount = (float) $this->discount_value;
        }

        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount     = $afterDiscount * ((float) $this->tax_rate / 100);
        $total         = $afterDiscount + $taxAmount + (float) $this->delivery_fee;

        $this->update([
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount'      => $taxAmount,
            'total'           => $total,
        ]);
    }

    // ─────────────────────────────────────────
    // Status Transition
    // ─────────────────────────────────────────

    /**
     * Transition to a new status with timestamp tracking.
     *
     * @throws \RuntimeException on invalid transition
     */
    public function transitionTo(OrderStatus $newStatus, int $byUserId): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \RuntimeException(
                "لا يمكن تغيير حالة الطلب من [{$this->status->label()}] إلى [{$newStatus->label()}]"
            );
        }

        $timestamps = match($newStatus) {
            OrderStatus::Confirmed  => ['confirmed_at'  => now()],
            OrderStatus::Ready      => ['ready_at'      => now()],
            OrderStatus::Dispatched => ['dispatched_at' => now()],
            OrderStatus::Delivered  => ['delivered_at'  => now()],
            OrderStatus::Cancelled  => ['cancelled_at'  => now(), 'cancelled_by' => $byUserId],
            default                 => [],
        };

        $this->update(array_merge(['status' => $newStatus, 'updated_by' => $byUserId], $timestamps));
    }



    // ─────────────────────────────────────────
    // Auto-generate order number
    // ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $date    = now()->format('Ymd');
        $lastSeq = static::whereDate('created_at', today())
                         ->lockForUpdate()
                         ->count();

        return sprintf('ORD-%s-%04d', $date, $lastSeq + 1);
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeForShift($query, int $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeForCashier($query, int $cashierId)
    {
        return $query->where('cashier_id', $cashierId);
    }

    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, OrderType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', array_map(
            fn ($s) => $s->value,
            array_filter(OrderStatus::cases(), fn ($s) => $s->isActive())
        ));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeExternal($query)
    {
        return $query->where('source', '!=', OrderSource::Pos->value);
    }

    public function scopeKitchenActive($query)
    {
        return $query->whereIn('status', [
            OrderStatus::Confirmed,
            OrderStatus::Preparing,
        ])->orderBy('created_at');
    }

    public function scopeCounterVisible(Builder $query): Builder
    {
        return $query
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereIn('status', [
                OrderStatus::Confirmed->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
            ]);
    }

    public function scopeForCounterLane(Builder $query, string $lane): Builder
    {
        $remainder = $lane === 'even' ? 0 : 1;

        return $query->whereRaw(
            "MOD(CAST(SUBSTRING_INDEX(order_number, '-', -1) AS UNSIGNED), 2) = ?",
            [$remainder],
        );
    }
}
