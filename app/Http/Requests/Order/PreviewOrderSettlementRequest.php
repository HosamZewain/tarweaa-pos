<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewOrderSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessPosSurface()
            && $this->user()?->hasPermission('orders.apply_special_settlement');
    }

    public function rules(): array
    {
        return [
            'scenario' => ['required', 'in:owner_charge,employee_allowance,employee_free_meal'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'charge_account_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'discount_type' => ['nullable', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:menu_item_variants,id'],
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.modifiers.*' => ['nullable', 'integer', 'min:1'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $scenario = $this->input('scenario');

                if ($scenario === 'owner_charge' && !$this->filled('charge_account_user_id')) {
                    $validator->errors()->add('charge_account_user_id', 'حساب المالك/الإدارة مطلوب لهذا النوع من التسوية.');
                }

                if (in_array($scenario, ['employee_allowance', 'employee_free_meal'], true) && !$this->filled('user_id')) {
                    $validator->errors()->add('user_id', 'الموظف المستفيد مطلوب لهذا النوع من التسوية.');
                }
            },
        ];
    }
}
