<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Opening = 'opening';
    case Sale    = 'sale';
    case Refund  = 'refund';
    case CashIn  = 'cash_in';
    case CashOut = 'cash_out';
    case Closing = 'closing';

    public function label(): string
    {
        return match($this) {
            self::Opening => 'رصيد افتتاحي',
            self::Sale    => 'مبيعات',
            self::Refund  => 'استرجاع',
            self::CashIn  => 'إيداع نقدي',
            self::CashOut => 'سحب نقدي',
            self::Closing => 'تسوية الإغلاق',
        };
    }

    public function direction(): CashMovementDirection
    {
        return match($this) {
            self::Opening, self::Sale, self::CashIn => CashMovementDirection::In,
            self::Refund, self::CashOut, self::Closing => CashMovementDirection::Out,
        };
    }

    public function isIn(): bool
    {
        return $this->direction() === CashMovementDirection::In;
    }

    public function isOut(): bool
    {
        return $this->direction() === CashMovementDirection::Out;
    }
}
