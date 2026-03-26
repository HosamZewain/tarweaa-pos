<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;

class OrderException extends \RuntimeException
{
    // ─── Cashier / Session Pre-checks ───────────────────────────────────────

    public static function cashierInactive(): self
    {
        return new self('حساب الكاشير غير نشط. يرجى التواصل مع المدير.');
    }

    public static function noActiveShift(): self
    {
        return new self('لا توجد وردية مفتوحة. لا يمكن إنشاء طلب بدون وردية نشطة.');
    }

    public static function noOpenDrawer(): self
    {
        return new self('لا توجد جلسة درج مفتوحة لهذا الكاشير. يرجى فتح الدرج أولاً.');
    }

    // ─── Order Content ───────────────────────────────────────────────────────

    public static function emptyOrder(): self
    {
        return new self('لا يمكن إتمام طلب فارغ. أضف منتجاً واحداً على الأقل.');
    }

    public static function deliveryAddressRequired(): self
    {
        return new self('طلبات التوصيل تتطلب إدخال عنوان التوصيل.');
    }

    public static function itemNotAvailable(string $itemName): self
    {
        return new self("المنتج [{$itemName}] غير متاح حالياً.");
    }

    public static function variantNotFound(): self
    {
        return new self('النوع المحدد غير موجود أو لا ينتمي لهذا المنتج.');
    }

    public static function itemQtyInvalid(): self
    {
        return new self('الكمية يجب أن تكون 1 على الأقل.');
    }

    // ─── Status Transitions ─────────────────────────────────────────────────

    public static function invalidTransition(OrderStatus $from, OrderStatus $to): self
    {
        return new self(
            "لا يمكن تغيير حالة الطلب من [{$from->label()}] إلى [{$to->label()}]."
        );
    }

    public static function alreadyCancelled(): self
    {
        return new self('هذا الطلب ملغي بالفعل.');
    }

    public static function notCancellable(OrderStatus $status): self
    {
        return new self(
            "لا يمكن إلغاء الطلب في حالة [{$status->label()}]."
        );
    }

    public static function alreadyRefunded(): self
    {
        return new self('تم استرجاع هذا الطلب بالفعل.');
    }

    public static function cannotRefundUnpaid(): self
    {
        return new self('لا يمكن استرجاع طلب غير مدفوع.');
    }

    public static function refundExceedsTotal(float $total, float $requested): self
    {
        return new self(
            sprintf(
                'مبلغ الاسترجاع (%.2f) يتجاوز إجمالي الطلب (%.2f).',
                $requested,
                $total,
            )
        );
    }

    // ─── Payments ────────────────────────────────────────────────────────────

    public static function insufficientPayment(float $required, float $provided): self
    {
        return new self(
            sprintf(
                'المبلغ المدفوع (%.2f) أقل من إجمالي الطلب (%.2f).',
                $provided,
                $required,
            )
        );
    }

    public static function orderAlreadyPaid(): self
    {
        return new self('هذا الطلب مدفوع بالكامل بالفعل.');
    }
}
