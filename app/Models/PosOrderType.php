<?php

namespace App\Models;

use App\Enums\ChannelPricingRuleType;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrderType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'source',
        'pricing_rule_type',
        'pricing_rule_value',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'pricing_rule_type' => ChannelPricingRuleType::class,
        'pricing_rule_value' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function channelPrices(): HasMany
    {
        return $this->hasMany(MenuItemChannelPrice::class);
    }
}
