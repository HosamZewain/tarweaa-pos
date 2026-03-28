<?php

namespace App\Enums;

enum OrderSettlementType: string
{
    case Standard = 'standard';
    case OwnerCharge = 'owner_charge';
    case EmployeeAllowance = 'employee_allowance';
    case EmployeeFreeMeal = 'employee_free_meal';
    case MixedBenefit = 'mixed_benefit';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'تسوية عادية',
            self::OwnerCharge => 'تحميل على مالك/إدارة',
            self::EmployeeAllowance => 'بدل موظف',
            self::EmployeeFreeMeal => 'ميزة وجبة مجانية',
            self::MixedBenefit => 'تسوية مختلطة',
        };
    }
}
