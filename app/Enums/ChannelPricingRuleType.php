<?php

namespace App\Enums;

enum ChannelPricingRuleType: string
{
    case BasePrice = 'base_price';
    case PercentageAdjustment = 'percentage_adjustment';
    case FixedAdjustment = 'fixed_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::BasePrice => 'السعر الأساسي المعتاد',
            self::PercentageAdjustment => 'نسبة على السعر',
            self::FixedAdjustment => 'مبلغ ثابت على السعر',
        };
    }
}
