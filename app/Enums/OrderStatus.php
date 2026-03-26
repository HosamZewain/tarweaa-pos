<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready     = 'ready';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded  = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'في الانتظار',
            self::Confirmed  => 'مؤكد',
            self::Preparing  => 'قيد التحضير',
            self::Ready      => 'جاهز',
            self::Dispatched => 'تم الإرسال',
            self::Delivered  => 'تم التوصيل',
            self::Cancelled  => 'ملغي',
            self::Refunded   => 'مسترجع',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending    => 'yellow',
            self::Confirmed  => 'blue',
            self::Preparing  => 'orange',
            self::Ready      => 'green',
            self::Dispatched => 'purple',
            self::Delivered  => 'teal',
            self::Cancelled  => 'red',
            self::Refunded   => 'gray',
        };
    }

    /**
     * Valid next statuses from this status.
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending    => [self::Confirmed, self::Cancelled],
            self::Confirmed  => [self::Preparing, self::Cancelled],
            self::Preparing  => [self::Ready, self::Cancelled],
            self::Ready      => [self::Dispatched, self::Delivered, self::Cancelled],
            self::Dispatched => [self::Delivered],
            self::Delivered  => [self::Refunded],
            default          => [],
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Refunded]);
    }

    public function isCancellable(): bool
    {
        return !$this->isFinal() && $this !== self::Delivered;
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Preparing, self::Ready, self::Dispatched]);
    }
}
