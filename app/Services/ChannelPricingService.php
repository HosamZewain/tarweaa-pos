<?php

namespace App\Services;

use App\Enums\ChannelPricingRuleType;
use App\Models\MenuItem;
use App\Models\MenuItemChannelPrice;
use App\Models\MenuItemVariant;
use App\Models\PosOrderType;
use Illuminate\Support\Collection;

class ChannelPricingService
{
    public function resolvePrice(
        MenuItem $menuItem,
        ?MenuItemVariant $variant = null,
        ?PosOrderType $posOrderType = null,
    ): float {
        $basePrice = (float) ($variant?->price ?? $menuItem->base_price ?? 0);

        if (!$posOrderType) {
            return round($basePrice, 2);
        }

        $overridePrice = $this->resolveOverridePrice($menuItem, $variant, $posOrderType);

        if ($overridePrice !== null) {
            return round($overridePrice, 2);
        }

        return round($this->applyDefaultRule($basePrice, $posOrderType), 2);
    }

    public function applyDefaultRule(float $basePrice, ?PosOrderType $posOrderType): float
    {
        if (!$posOrderType) {
            return $basePrice;
        }

        $ruleType = $posOrderType->pricing_rule_type ?? ChannelPricingRuleType::BasePrice;
        $ruleValue = (float) ($posOrderType->pricing_rule_value ?? 0);

        return match ($ruleType) {
            ChannelPricingRuleType::PercentageAdjustment => max(0, $basePrice + ($basePrice * ($ruleValue / 100))),
            ChannelPricingRuleType::FixedAdjustment => max(0, $basePrice + $ruleValue),
            default => $basePrice,
        };
    }

    public function ruleSummary(?PosOrderType $posOrderType): string
    {
        if (!$posOrderType) {
            return 'السعر الأساسي المعتاد';
        }

        $ruleType = $posOrderType->pricing_rule_type ?? ChannelPricingRuleType::BasePrice;
        $ruleValue = (float) ($posOrderType->pricing_rule_value ?? 0);

        return match ($ruleType) {
            ChannelPricingRuleType::PercentageAdjustment => sprintf('نسبة %s%s على السعر', $ruleValue > 0 ? '+' : '', rtrim(rtrim(number_format($ruleValue, 2, '.', ''), '0'), '.')),
            ChannelPricingRuleType::FixedAdjustment => sprintf('مبلغ %s%s ج.م', $ruleValue > 0 ? '+' : '', rtrim(rtrim(number_format($ruleValue, 2, '.', ''), '0'), '.')),
            default => 'السعر الأساسي المعتاد',
        };
    }

    private function resolveOverridePrice(
        MenuItem $menuItem,
        ?MenuItemVariant $variant,
        PosOrderType $posOrderType,
    ): ?float {
        $overrides = $this->getOverrides($menuItem);

        if ($variant) {
            $variantOverride = $overrides
                ->first(fn (MenuItemChannelPrice $price) => (int) $price->pos_order_type_id === (int) $posOrderType->id
                    && (int) $price->menu_item_variant_id === (int) $variant->id);

            if ($variantOverride) {
                return (float) $variantOverride->price;
            }
        }

        $itemOverride = $overrides
            ->first(fn (MenuItemChannelPrice $price) => (int) $price->pos_order_type_id === (int) $posOrderType->id
                && $price->menu_item_variant_id === null);

        return $itemOverride ? (float) $itemOverride->price : null;
    }

    /**
     * @return Collection<int, MenuItemChannelPrice>
     */
    private function getOverrides(MenuItem $menuItem): Collection
    {
        if ($menuItem->relationLoaded('channelPrices')) {
            return $menuItem->channelPrices->sortByDesc('id')->values();
        }

        return $menuItem->channelPrices()
            ->latest('id')
            ->get();
    }
}
