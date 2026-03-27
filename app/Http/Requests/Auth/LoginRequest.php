<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username'    => ['required', 'string'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required'    => 'اسم المستخدم أو البريد الإلكتروني مطلوب.',
            'password.required'    => 'كلمة المرور مطلوبة.',
            'device_name.required' => 'اسم الجهاز مطلوب.',
        ];
    }
}
