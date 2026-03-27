<?php

namespace App\Http\Requests\Drawer;

use Illuminate\Foundation\Http\FormRequest;

class CloseDrawerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'preview_token' => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_cash.required' => 'المبلغ الفعلي مطلوب.',
            'actual_cash.numeric'  => 'المبلغ الفعلي يجب أن يكون رقماً.',
            'actual_cash.min'      => 'المبلغ الفعلي لا يمكن أن يكون سالباً.',
        ];
    }
}
