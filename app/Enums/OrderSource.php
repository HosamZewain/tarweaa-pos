<?php

namespace App\Enums;

enum OrderSource: string
{
    case Pos           = 'pos';
    case Talabat       = 'talabat';
    case Jahez         = 'jahez';
    case HungerStation = 'hungerstation';
    case Other         = 'other';

    public function label(): string
    {
        return match($this) {
            self::Pos           => 'نقطة البيع',
            self::Talabat       => 'طلبات',
            self::Jahez         => 'جاهز',
            self::HungerStation => 'هنقر ستيشن',
            self::Other         => 'أخرى',
        };
    }

    public function isExternal(): bool
    {
        return $this !== self::Pos;
    }
}
