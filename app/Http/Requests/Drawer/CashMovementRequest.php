<?php

namespace App\Http\Requests\Drawer;

use Illuminate\Foundation\Http\FormRequest;

class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes'  => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'المبلغ مطلوب.',
            'amount.numeric'  => 'المبلغ يجب أن يكون رقماً.',
            'amount.gt'       => 'المبلغ يجب أن يكون أكبر من صفر.',
            'notes.required'  => 'الملاحظات مطلوبة.',
        ];
    }
}
