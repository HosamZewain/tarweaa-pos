<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderSource;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pos_order_type_id'     => ['nullable', 'integer', Rule::exists('pos_order_types', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'type'                  => ['required', Rule::in(array_column(OrderType::cases(), 'value'))],
            'source'                => ['nullable', Rule::in(array_column(OrderSource::cases(), 'value'))],
            'customer_id'           => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name'         => ['nullable', 'string', 'max:255'],
            'customer_phone'        => ['nullable', 'string', 'max:20'],
            'delivery_address'      => ['nullable', 'string', 'max:1000'],
            'delivery_fee'          => ['nullable', 'numeric', 'min:0'],
            'discount_type'         => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value'        => ['nullable', 'numeric', 'min:0'],
            'tax_rate'              => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'scheduled_at'          => ['nullable', 'date'],
            'external_order_id'     => ['nullable', 'string', 'max:255'],
            'external_order_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'pos_order_type_id.exists' => 'نوع الطلب المحدد غير موجود أو غير نشط.',
            'type.required'     => 'نوع الطلب مطلوب.',
            'type.in'           => 'نوع الطلب غير صالح.',
            'customer_id.exists' => 'العميل المحدد غير موجود.',
        ];
    }
}
