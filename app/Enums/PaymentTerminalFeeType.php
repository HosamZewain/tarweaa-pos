<?php

namespace App\Enums;

enum PaymentTerminalFeeType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case PercentagePlusFixed = 'percentage_plus_fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'نسبة مئوية',
            self::Fixed => 'مبلغ ثابت',
            self::PercentagePlusFixed => 'نسبة + مبلغ ثابت',
        };
    }
}
