<?php

namespace App\Http\Requests\Drawer;

use Illuminate\Foundation\Http\FormRequest;

class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes'  => ['required', 'string', 'max:1000'],
            'approver_id' => ['required', 'integer', 'exists:users,id'],
            'approver_pin' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'المبلغ مطلوب.',
            'amount.numeric'  => 'المبلغ يجب أن يكون رقماً.',
            'amount.gt'       => 'المبلغ يجب أن يكون أكبر من صفر.',
            'notes.required'  => 'الملاحظات مطلوبة.',
            'approver_id.required' => 'اختيار المدير أو الأدمن المعتمد مطلوب.',
            'approver_id.exists' => 'المستخدم المعتمد المحدد غير موجود.',
            'approver_pin.required' => 'رمز اعتماد المدير مطلوب.',
        ];
    }
}
