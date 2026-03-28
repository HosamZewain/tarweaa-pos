<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class ListSettlementUsersRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
