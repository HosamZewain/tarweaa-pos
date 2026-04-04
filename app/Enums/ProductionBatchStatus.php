<?php

namespace App\Enums;

enum ProductionBatchStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
        };
    }
}
