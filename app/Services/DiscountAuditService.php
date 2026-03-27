<?php

namespace App\Services;

use App\Models\DiscountLog;
use App\Models\Order;
use App\Models\OrderItem;

class DiscountAuditService
{
    public function logOrderDiscount(
        Order $order,
        ?int $appliedBy,
        string $action = 'applied',
        ?float $previousDiscountAmount = null,
        ?int $requestedBy = null,
        ?string $reason = null,
    ): ?DiscountLog {
        $hasConfiguredDiscount = $order->discount_type !== null || (float) $order->discount_value > 0;
        $hasEffectiveDiscount = (float) $order->discount_amount > 0;

        if (!$hasConfiguredDiscount && !$hasEffectiveDiscount && $action !== 'removed') {
            return null;
        }

        return DiscountLog::create([
            'order_id' => $order->id,
            'applied_by' => $appliedBy,
            'requested_by' => $requestedBy,
            'scope' => 'order',
            'action' => $action,
            'discount_type' => $order->discount_type,
            'discount_value' => (float) ($order->discount_value ?? 0),
            'discount_amount' => (float) ($order->discount_amount ?? 0),
            'previous_discount_amount' => $previousDiscountAmount,
            'reason' => $reason,
        ]);
    }

    public function logItemDiscount(OrderItem $item, ?int $appliedBy): ?DiscountLog
    {
        if ((float) $item->discount_amount <= 0) {
            return null;
        }

        return DiscountLog::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'applied_by' => $appliedBy,
            'scope' => 'item',
            'action' => 'item_applied',
            'discount_type' => 'fixed',
            'discount_value' => (float) $item->discount_amount,
            'discount_amount' => (float) $item->discount_amount,
        ]);
    }
}
