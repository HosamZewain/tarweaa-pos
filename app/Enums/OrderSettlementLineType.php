<?php

namespace App\Enums;

enum OrderSettlementLineType: string
{
    case OwnerCharge = 'owner_charge';
    case EmployeeMonthlyAllowance = 'employee_monthly_allowance';
    case EmployeeFreeMealAmount = 'employee_free_meal_amount';
    case EmployeeFreeMealCount = 'employee_free_meal_count';

    public function label(): string
    {
        return match ($this) {
            self::OwnerCharge => 'تحميل على حساب مالك/إدارة',
            self::EmployeeMonthlyAllowance => 'استهلاك بدل شهري',
            self::EmployeeFreeMealAmount => 'استهلاك ميزة مبلغ وجبة',
            self::EmployeeFreeMealCount => 'استهلاك ميزة عدد وجبات',
        };
    }
}
