<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

class RecordExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'    => ['required', 'integer', 'exists:expense_categories,id'],
            'amount'         => ['required', 'numeric', 'gt:0'],
            'description'    => ['required', 'string', 'max:1000'],
            'expense_date'   => ['required', 'date'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'  => 'فئة المصروف مطلوبة.',
            'category_id.exists'    => 'فئة المصروف المحددة غير موجودة.',
            'amount.required'       => 'مبلغ المصروف مطلوب.',
            'amount.gt'             => 'مبلغ المصروف يجب أن يكون أكبر من صفر.',
            'description.required'  => 'وصف المصروف مطلوب.',
            'expense_date.required' => 'تاريخ المصروف مطلوب.',
            'expense_date.date'     => 'تاريخ المصروف غير صالح.',
        ];
    }
}
