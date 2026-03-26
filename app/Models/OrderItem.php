<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_variant_id',
        'item_name',
        'variant_name',
        'unit_price',
        'cost_price',
        'quantity',
        'discount_amount',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'status'          => OrderItemStatus::class,
        'unit_price'      => 'decimal:2',
        'cost_price'      => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total'           => 'decimal:2',
        'quantity'        => 'integer',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    /**
     * Recalculate line total from unit_price × quantity + modifiers - discount.
     */
    public function recalculate(): void
    {
        $modifiersTotal = (float) $this->modifiers()->sum(
            \DB::raw('price * quantity')
        );

        $total = ((float) $this->unit_price * $this->quantity)
            + $modifiersTotal
            - (float) $this->discount_amount;

        $this->update(['total' => max(0, $total)]);
    }

    /**
     * Snapshot data from the menu item at the moment of order creation.
     * Prevents price changes from affecting historical orders.
     */
    public static function snapshotFrom(MenuItem $item, ?MenuItemVariant $variant = null): array
    {
        return [
            'item_name'   => $item->name,
            'variant_name' => $variant?->name,
            'unit_price'  => $variant ? $variant->price : $item->base_price,
            'cost_price'  => $variant ? $variant->cost_price : $item->cost_price,
        ];
    }

    public function grossProfit(): float
    {
        if (!$this->cost_price) {
            return 0;
        }

        return (float) $this->total - ((float) $this->cost_price * $this->quantity);
    }

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', '!=', OrderItemStatus::Cancelled);
    }
}
