<?php

namespace App\Filament\Concerns;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;

trait FormatsAdminDetailValues
{
    public function formatMoney(float | int | string | null $value): string
    {
        return number_format((float) ($value ?? 0), 2) . ' ج.م';
    }

    public function formatNumber(float | int | string | null $value): string
    {
        return number_format((float) ($value ?? 0), (float) $value === floor((float) $value) ? 0 : 2);
    }

    public function formatDateTime(CarbonInterface | string | null $value): string
    {
        return BusinessTime::formatDateTime($value, 'Y/m/d h:i A');
    }

    public function formatDate(CarbonInterface | string | null $value): string
    {
        return BusinessTime::formatDate($value, 'Y/m/d');
    }

    public function differenceTone(float | int | string | null $value): string
    {
        $value = (float) ($value ?? 0);

        return match (true) {
            $value > 0 => 'warning',
            $value < 0 => 'danger',
            default => 'success',
        };
    }

    public function orderStatusTone(OrderStatus | string | null $status): string
    {
        $value = $status instanceof OrderStatus ? $status->value : $status;

        return match ($value) {
            OrderStatus::Pending->value => 'warning',
            OrderStatus::Confirmed->value => 'info',
            OrderStatus::Preparing->value => 'primary',
            OrderStatus::Ready->value, OrderStatus::Delivered->value => 'success',
            OrderStatus::Dispatched->value => 'neutral',
            OrderStatus::Cancelled->value, OrderStatus::Refunded->value => 'danger',
            default => 'neutral',
        };
    }

    public function paymentStatusTone(PaymentStatus | string | null $status): string
    {
        $value = $status instanceof PaymentStatus ? $status->value : $status;

        return match ($value) {
            PaymentStatus::Paid->value => 'success',
            PaymentStatus::Partial->value => 'warning',
            PaymentStatus::Refunded->value => 'danger',
            PaymentStatus::Unpaid->value => 'neutral',
            default => 'neutral',
        };
    }

    public function paymentMethodTone(PaymentMethod | string | null $method): string
    {
        $value = $method instanceof PaymentMethod ? $method->value : $method;

        return match ($value) {
            PaymentMethod::Cash->value => 'success',
            PaymentMethod::Card->value => 'primary',
            default => 'neutral',
        };
    }

    public function drawerStatusTone(DrawerSessionStatus | string | null $status): string
    {
        $value = $status instanceof DrawerSessionStatus ? $status->value : $status;

        return $value === DrawerSessionStatus::Open->value ? 'success' : 'neutral';
    }

    public function shiftStatusTone(ShiftStatus | string | null $status): string
    {
        $value = $status instanceof ShiftStatus ? $status->value : $status;

        return $value === ShiftStatus::Open->value ? 'success' : 'neutral';
    }
}
