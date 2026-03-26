<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderSource;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateExternalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source'                => [
                'required',
                Rule::in([
                    OrderSource::Talabat->value,
                    OrderSource::Jahez->value,
                    OrderSource::HungerStation->value,
                    OrderSource::Other->value,
                ]),
            ],
            'drawer_session_id'     => ['required', 'integer', 'exists:cashier_drawer_sessions,id'],
            'customer_name'         => ['nullable', 'string', 'max:255'],
            'customer_phone'        => ['nullable', 'string', 'max:20'],
            'delivery_address'      => ['nullable', 'string', 'max:1000'],
            'delivery_fee'          => ['nullable', 'numeric', 'min:0'],
            'tax_rate'              => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_type'         => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value'        => ['nullable', 'numeric', 'min:0'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'external_order_id'     => ['required', 'string', 'max:255'],
            'external_order_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'source.required'              => 'مصدر الطلب مطلوب.',
            'source.in'                    => 'مصدر الطلب يجب أن يكون من منصة خارجية.',
            'drawer_session_id.required'   => 'معرّف جلسة الدرج مطلوب.',
            'drawer_session_id.exists'     => 'جلسة الدرج المحددة غير موجودة.',
            'external_order_id.required'   => 'رقم الطلب من المنصة الخارجية مطلوب.',
        ];
    }
}
