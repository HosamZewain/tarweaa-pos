<?php

namespace App\Http\Requests\Drawer;

use Illuminate\Foundation\Http\FormRequest;

class OpenDrawerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->user() && !$this->filled('cashier_id')) {
            $this->merge([
                'cashier_id' => $this->user()->id,
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cashier_id'      => ['required', 'integer', 'exists:users,id'],
            'shift_id'        => ['required', 'integer', 'exists:shifts,id'],
            'pos_device_id'   => ['required', 'integer', 'exists:pos_devices,id'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'cashier_id.required'    => 'معرّف الكاشير مطلوب.',
            'cashier_id.exists'      => 'الكاشير المحدد غير موجود.',
            'shift_id.required'      => 'معرّف الوردية مطلوب.',
            'shift_id.exists'        => 'الوردية المحددة غير موجودة.',
            'pos_device_id.required' => 'معرّف جهاز نقطة البيع مطلوب.',
            'pos_device_id.exists'   => 'جهاز نقطة البيع المحدد غير موجود.',
            'opening_balance.min'    => 'رصيد الافتتاح لا يمكن أن يكون سالباً.',
        ];
    }
}
