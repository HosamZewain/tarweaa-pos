<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case Purchase      = 'purchase';
    case SaleDeduction = 'sale_deduction';
    case Adjustment    = 'adjustment';
    case Waste         = 'waste';
    case Return        = 'return';
    case TransferIn    = 'transfer_in';
    case TransferOut   = 'transfer_out';

    public function label(): string
    {
        return match($this) {
            self::Purchase      => 'مشتريات',
            self::SaleDeduction => 'خصم مبيعات',
            self::Adjustment    => 'تعديل يدوي',
            self::Waste         => 'هالك',
            self::Return        => 'مرتجع',
            self::TransferIn    => 'تحويل وارد',
            self::TransferOut   => 'تحويل صادر',
        };
    }

    public function isStockIncrease(): bool
    {
        return in_array($this, [self::Purchase, self::Return, self::TransferIn]);
    }

    public function isStockDecrease(): bool
    {
        return !$this->isStockIncrease() && $this !== self::Adjustment;
    }
}
