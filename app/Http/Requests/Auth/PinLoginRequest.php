<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PinLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin'         => ['required', 'string', 'min:4', 'max:6'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.required'         => 'رمز PIN مطلوب.',
            'pin.min'              => 'رمز PIN يجب أن يكون 4 أرقام على الأقل.',
            'pin.max'              => 'رمز PIN يجب أن لا يتجاوز 6 أرقام.',
            'device_name.required' => 'اسم الجهاز مطلوب.',
        ];
    }
}
