<?php

namespace App\Enums;

enum UserMealBenefitFreeMealType: string
{
    case Count = 'count';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Count => 'عدد وجبات',
            self::Amount => 'حد أقصى بالمبلغ',
        };
    }
}
