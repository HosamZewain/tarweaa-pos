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
            'value' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'  => 'نوع الخصم مطلوب.',
            'type.in'        => 'نوع الخصم يجب أن يكون fixed أو percentage.',
            'value.required' => 'قيمة الخصم مطلوبة.',
            'value.min'      => 'قيمة الخصم لا يمكن أن تكون سالبة.',
        ];
    }
}
