<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'  => ['required', Rule::in(['fixed', 'percentage'])],
            'value' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
            'approval_token' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'  => 'نوع الخصم مطلوب.',
            'type.in'        => 'نوع الخصم يجب أن يكون fixed أو percentage.',
            'value.required' => 'قيمة الخصم مطلوبة.',
            'value.min'      => 'قيمة الخصم يجب أن تكون أكبر من صفر.',
            'reason.required' => 'سبب الخصم مطلوب.',
            'reason.max' => 'سبب الخصم يجب ألا يتجاوز 1000 حرف.',
            'approval_token.required' => 'موافقة المدير مطلوبة قبل تطبيق الخصم.',
        ];
    }
}
