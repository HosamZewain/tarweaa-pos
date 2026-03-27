<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function resolveRecord(int | string $key): Order
    {
        /** @var Order $record */
        $record = parent::resolveRecord($key);

        return $record->load([
            'shift',
            'drawerSession',
            'posDevice',
            'cashier',
            'customer',
            'refunder',
            'canceller',
            'items.modifiers',
            'payments',
            'orderDiscountLogs.appliedBy',
            'orderDiscountLogs.requestedBy',
            'latestOrderDiscountLog.appliedBy',
            'latestOrderDiscountLog.requestedBy',
        ]);
    }
}
