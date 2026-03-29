<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemChannelPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'pos_order_type_id',
        'menu_item_variant_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function posOrderType(): BelongsTo
    {
        return $this->belongsTo(PosOrderType::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id');
    }
}
