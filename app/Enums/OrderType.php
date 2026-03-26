<?php

namespace App\Enums;

enum OrderType: string
{
    case Takeaway = 'takeaway';
    case Pickup   = 'pickup';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match($this) {
            self::Takeaway => 'تيك أواي',
            self::Pickup   => 'استلام من الفرع',
            self::Delivery => 'توصيل',
        };
    }

    public function requiresDeliveryAddress(): bool
    {
        return $this === self::Delivery;
    }

    public function requiresDeliveryFee(): bool
    {
        return $this === self::Delivery;
    }
}
