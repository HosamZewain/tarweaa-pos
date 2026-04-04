<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum UserMealBenefitPeriodType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'يومي',
            self::Weekly => 'أسبوعي',
            self::Monthly => 'شهري',
        };
    }

    public function limitLabel(): string
    {
        return match ($this) {
            self::Daily => 'لكل يوم',
            self::Weekly => 'لكل أسبوع',
            self::Monthly => 'لكل شهر',
        };
    }

    public function windowLabel(Carbon $reference): string
    {
        return match ($this) {
            self::Daily => 'اليوم ' . $reference->translatedFormat('Y/m/d'),
            self::Weekly => 'أسبوع ' . $reference->copy()->startOfWeek()->translatedFormat('Y/m/d')
                . ' - ' . $reference->copy()->endOfWeek()->translatedFormat('Y/m/d'),
            self::Monthly => $reference->translatedFormat('F Y'),
        };
    }
}
