<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid   = 'unpaid';
    case Paid     = 'paid';
    case Partial  = 'partial';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Unpaid   => 'غير مدفوع',
            self::Paid     => 'مدفوع',
            self::Partial  => 'مدفوع جزئياً',
            self::Refunded => 'مسترجع',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function requiresCollection(): bool
    {
        return in_array($this, [self::Unpaid, self::Partial]);
    }
}
