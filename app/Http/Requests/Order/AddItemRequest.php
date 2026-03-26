<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class AddItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_item_id'    => ['required', 'integer', 'exists:menu_items,id'],
            'quantity'        => ['nullable', 'integer', 'min:1'],
            'variant_id'     => ['nullable', 'integer', 'exists:menu_item_variants,id'],
            'modifiers'       => ['nullable', 'array'],
            'modifiers.*'     => ['integer', 'min:1'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_item_id.required' => 'معرّف المنتج مطلوب.',
            'menu_item_id.exists'   => 'المنتج المحدد غير موجود.',
            'quantity.min'          => 'الكمية يجب أن تكون 1 على الأقل.',
            'variant_id.exists'     => 'النوع المحدد غير موجود.',
        ];
    }
}
