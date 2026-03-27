<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthorizeDiscountApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['fixed', 'percentage'])],
            'value' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
            'approver_id' => ['required', 'integer', 'exists:users,id'],
            'approver_pin' => ['required', 'string', 'min:4', 'max:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'نوع الخصم مطلوب.',
            'value.required' => 'قيمة الخصم مطلوبة.',
            'value.min' => 'قيمة الخصم يجب أن تكون أكبر من صفر.',
            'reason.required' => 'سبب الخصم مطلوب.',
            'reason.max' => 'سبب الخصم يجب ألا يتجاوز 1000 حرف.',
            'approver_id.required' => 'يجب اختيار مدير لاعتماد الخصم.',
            'approver_id.exists' => 'المدير المحدد غير موجود.',
            'approver_pin.required' => 'رمز اعتماد المدير مطلوب.',
            'approver_pin.min' => 'رمز الاعتماد يجب أن يكون 4 أرقام على الأقل.',
            'approver_pin.max' => 'رمز الاعتماد يجب ألا يتجاوز 6 أرقام.',
        ];
    }
}
