<?php

namespace App\Enums;

enum MealBenefitLedgerEntryType: string
{
    case OwnerChargeUsage = 'owner_charge_usage';
    case MonthlyAllowanceUsage = 'monthly_allowance_usage';
    case FreeMealUsage = 'free_meal_usage';
    case SupplementalPayment = 'supplemental_payment';
    case ManualAdjustment = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::OwnerChargeUsage => 'تحميل طلب على حساب',
            self::MonthlyAllowanceUsage => 'استهلاك بدل شهري',
            self::FreeMealUsage => 'استهلاك ميزة وجبة مجانية',
            self::SupplementalPayment => 'دفع تكميلي من المستفيد',
            self::ManualAdjustment => 'تسوية يدوية',
        };
    }
}
