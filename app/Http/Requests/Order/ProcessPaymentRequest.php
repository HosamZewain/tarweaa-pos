<?php

namespace App\Http\Requests\Order;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payments'                    => ['required', 'array', 'min:1'],
            'payments.*.method'           => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'payments.*.amount'           => ['required', 'numeric', 'gt:0'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'payments.required'           => 'بيانات الدفع مطلوبة.',
            'payments.min'                => 'يجب تقديم طريقة دفع واحدة على الأقل.',
            'payments.*.method.required'  => 'طريقة الدفع مطلوبة.',
            'payments.*.method.in'        => 'طريقة الدفع غير صالحة.',
            'payments.*.amount.required'  => 'مبلغ الدفع مطلوب.',
            'payments.*.amount.gt'        => 'مبلغ الدفع يجب أن يكون أكبر من صفر.',
        ];
    }
}
