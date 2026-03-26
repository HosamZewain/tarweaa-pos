<?php

namespace App\Exceptions;

use Illuminate\Support\Collection;

class ShiftException extends \RuntimeException
{
    public static function noActiveShift(): self
    {
        return new self('لا توجد وردية مفتوحة حالياً. يرجى فتح وردية أولاً.');
    }

    public static function alreadyOpen(): self
    {
        return new self('يوجد وردية مفتوحة بالفعل. لا يمكن فتح أكثر من وردية في نفس الوقت.');
    }

    public static function alreadyClosed(): self
    {
        return new self('هذه الوردية مغلقة بالفعل.');
    }

    public static function cannotCloseWithOpenDrawers(Collection $cashierNames): self
    {
        $names = $cashierNames->implode('، ');

        return new self(
            "لا يمكن إغلاق الوردية. الكاشيرات التالية لديهم دراجات مفتوحة: {$names}"
        );
    }

    public static function unauthorized(string $action): self
    {
        return new self("غير مصرح لك بـ: {$action}", 403);
    }
}
