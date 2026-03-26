<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Pending   = 'pending';
    case Preparing = 'preparing';
    case Ready     = 'ready';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'في الانتظار',
            self::Preparing => 'قيد التحضير',
            self::Ready     => 'جاهز',
            self::Cancelled => 'ملغي',
        };
    }
}
