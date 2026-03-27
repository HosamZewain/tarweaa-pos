<?php

namespace App\Exceptions;

class DrawerException extends \RuntimeException
{
    public static function alreadyOpen(string $cashierName): self
    {
        return new self(
            "الكاشير [{$cashierName}] لديه درج مفتوح بالفعل. لا يمكن فتح أكثر من درج واحد لنفس الكاشير."
        );
    }

    public static function noActiveSession(): self
    {
        return new self('لا توجد جلسة درج مفتوحة لهذا الكاشير.');
    }

    public static function sessionClosed(): self
    {
        return new self('جلسة الدرج مغلقة بالفعل. لا يمكن إجراء عمليات على درج مغلق.');
    }

    public static function shiftNotOpen(): self
    {
        return new self('لا يمكن فتح درج على وردية مغلقة.');
    }

    public static function sessionNotFound(int $sessionId): self
    {
        return new self("جلسة الدرج رقم [{$sessionId}] غير موجودة.");
    }

    public static function insufficientBalance(float $available, float $requested): self
    {
        return new self(
            sprintf(
                'رصيد الدرج غير كافٍ. المتاح: %.2f — المطلوب: %.2f',
                $available,
                $requested,
            )
        );
    }

    public static function negativeAmount(): self
    {
        return new self('المبلغ يجب أن يكون أكبر من صفر.');
    }

    public static function deviceInactive(string $deviceName): self
    {
        return new self("جهاز نقطة البيع [{$deviceName}] غير نشط.");
    }

    public static function unauthorized(string $action): self
    {
        return new self("غير مصرح لك بـ: {$action}", 403);
    }

    public static function closeDeclarationRequired(): self
    {
        return new self('يجب مراجعة الجرد وتأكيد المبلغ المُعلن قبل إغلاق الدرج.', 422);
    }

    public static function varianceReasonRequired(): self
    {
        return new self('سبب الفرق مطلوب عند وجود عجز أو فائض قبل إغلاق الدرج.', 422);
    }

    public static function reconciliationLocked(): self
    {
        return new self('تم بدء جرد إغلاق الدرج بالفعل. يجب إكمال الإغلاق أولاً قبل العودة إلى نقطة البيع.', 423);
    }
}
