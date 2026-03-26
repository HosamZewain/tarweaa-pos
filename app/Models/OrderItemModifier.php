<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemModifier extends Model
{
    protected $fillable = [
        'order_item_id',
        'menu_item_modifier_id',
        'modifier_name',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'quantity' => 'integer',
    ];

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(MenuItemModifier::class, 'menu_item_modifier_id');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    public function lineTotal(): float
    {
        return (float) $this->price * $this->quantity;
    }

    /**
     * Snapshot from a MenuItemModifier at the moment of order creation.
     */
    public static function snapshotFrom(MenuItemModifier $modifier): array
    {
        return [
            'modifier_name' => $modifier->name,
            'price'         => $modifier->price,
        ];
    }
}
