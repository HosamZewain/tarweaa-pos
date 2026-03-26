<?php

namespace App\Enums;

enum DrawerSessionStatus: string
{
    case Open   = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match($this) {
            self::Open   => 'مفتوح',
            self::Closed => 'مغلق',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
