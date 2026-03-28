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

    public static function invalidPosOrderType(): self
    {
        return new self('نوع الطلب المحدد غير صالح أو غير نشط.', 422);
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

    public static function discountPermissionRequired(): self
    {
        return new self('ليس لديك صلاحية لتطبيق الخصم على الطلب.', 403);
    }

    public static function discountApprovalRequired(): self
    {
        return new self('يلزم اعتماد المدير قبل تطبيق الخصم.', 403);
    }

    public static function discountApproverInvalid(): self
    {
        return new self('المستخدم المحدد لا يمكنه اعتماد الخصم.', 403);
    }

    public static function discountApproverPinInvalid(): self
    {
        return new self('رمز اعتماد المدير غير صحيح.', 403);
    }

    public static function invalidPaymentTerminal(): self
    {
        return new self('جهاز الدفع الإلكتروني المحدد غير صالح أو غير نشط.', 422);
    }

    public static function settlementNotAllowedAfterPayment(): self
    {
        return new self('لا يمكن تعديل تسوية الطلب بعد تسجيل دفعات فعلية عليه.', 422);
    }

    public static function invalidOwnerChargeAccount(): self
    {
        return new self('الحساب المحدد غير صالح لاستقبال أوامر التحميل على المالك/الإدارة.', 422);
    }

    public static function mealBenefitProfileRequired(): self
    {
        return new self('لا يوجد ملف مزايا وجبات نشط لهذا المستخدم.', 422);
    }

    public static function monthlyAllowanceNotEnabled(): self
    {
        return new self('البدل الشهري غير مفعل لهذا المستخدم.', 422);
    }

    public static function freeMealBenefitNotEnabled(): self
    {
        return new self('ميزة الوجبة المجانية غير مفعلة لهذا المستخدم.', 422);
    }

    public static function noEligibleBenefitItems(): self
    {
        return new self('لا توجد أصناف مؤهلة للاستفادة من ميزة الوجبة المجانية في هذا الطلب.', 422);
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

    public static function paymentReferenceRequired(): self
    {
        return new self('رقم المرجع أو الإيصال مطلوب لعمليات البطاقة.', 422);
    }

    public static function handoverRequiresPaidOrder(): self
    {
        return new self('لا يمكن تسليم طلب غير مدفوع.', 422);
    }

    public static function handoverRequiresReadyOrder(): self
    {
        return new self('لا يمكن تسليم الطلب قبل أن يصبح جاهزًا.', 422);
    }
}
