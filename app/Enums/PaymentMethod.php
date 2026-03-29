<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash       = 'cash';
    case Card       = 'card';
    case Online     = 'online';
    case InstaPay   = 'instapay';
    case TalabatPay = 'talabat_pay';
    case JahezPay   = 'jahez_pay';
    case Other      = 'other';

    public function label(): string
    {
        return match($this) {
            self::Cash       => 'نقدي',
            self::Card       => 'بطاقة',
            self::Online     => 'دفع إلكتروني',
            self::InstaPay   => 'إنستاباي',
            self::TalabatPay => 'دفع طلبات',
            self::JahezPay   => 'دفع جاهز',
            self::Other      => 'أخرى',
        };
    }

    public function affectsCashDrawer(): bool
    {
        return $this === self::Cash;
    }
}
