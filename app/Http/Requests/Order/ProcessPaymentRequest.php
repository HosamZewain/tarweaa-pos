<?php

namespace App\Http\Requests\Order;

use App\Enums\PaymentMethod;
use App\Models\PaymentTerminal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'payments.*.terminal_id'      => ['nullable', 'integer', 'exists:payment_terminals,id'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (($this->input('payments') ?? []) as $index => $payment) {
                    $method = $payment['method'] ?? null;
                    $terminalId = $payment['terminal_id'] ?? null;
                    $reference = trim((string) ($payment['reference_number'] ?? ''));

                    if ($method !== PaymentMethod::Card->value) {
                        continue;
                    }

                    if (!$terminalId) {
                        $validator->errors()->add("payments.$index.terminal_id", 'جهاز الدفع الإلكتروني مطلوب لعمليات البطاقة.');
                    } else {
                        $terminal = PaymentTerminal::query()->find($terminalId);

                        if (!$terminal || !$terminal->is_active) {
                            $validator->errors()->add("payments.$index.terminal_id", 'جهاز الدفع الإلكتروني المحدد غير نشط أو غير موجود.');
                        }
                    }

                    if ($reference === '') {
                        $validator->errors()->add("payments.$index.reference_number", 'رقم المرجع أو الإيصال مطلوب لعمليات البطاقة.');
                    }
                }
            },
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
            'payments.*.terminal_id.exists' => 'جهاز الدفع الإلكتروني المحدد غير موجود.',
        ];
    }
}
