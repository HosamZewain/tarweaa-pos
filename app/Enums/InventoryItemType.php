<?php

namespace App\Enums;

enum InventoryItemType: string
{
    case RawMaterial = 'raw_material';
    case PreparedItem = 'prepared_item';

    public function label(): string
    {
        return match ($this) {
            self::RawMaterial => 'مادة خام',
            self::PreparedItem => 'منتج مُحضّر',
        };
    }
}
