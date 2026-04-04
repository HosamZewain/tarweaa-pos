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
    case ProductionConsumption = 'production_consumption';
    case ProductionOutput = 'production_output';
    case ProductionVoidOutput = 'production_void_output';
    case ProductionVoidInputReturn = 'production_void_input_return';

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
            self::ProductionConsumption => 'استهلاك إنتاج',
            self::ProductionOutput => 'ناتج إنتاج',
            self::ProductionVoidOutput => 'عكس ناتج إنتاج',
            self::ProductionVoidInputReturn => 'رد مدخلات إنتاج',
        };
    }

    public function isStockIncrease(): bool
    {
        return in_array($this, [self::Purchase, self::Return, self::TransferIn, self::ProductionOutput, self::ProductionVoidInputReturn], true);
    }

    public function isStockDecrease(): bool
    {
        return !$this->isStockIncrease() && $this !== self::Adjustment;
    }
}
