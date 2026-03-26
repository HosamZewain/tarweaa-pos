<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class RefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'مبلغ الاسترجاع مطلوب.',
            'amount.gt'       => 'مبلغ الاسترجاع يجب أن يكون أكبر من صفر.',
            'reason.required' => 'سبب الاسترجاع مطلوب.',
        ];
    }
}
