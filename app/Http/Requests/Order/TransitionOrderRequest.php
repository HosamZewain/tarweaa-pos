<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        if (in_array($this->input('status'), [OrderStatus::Preparing->value, OrderStatus::Ready->value], true)) {
            return $user->hasPermission('mark_order_ready');
        }

        if (in_array($this->input('status'), [OrderStatus::Dispatched->value, OrderStatus::Delivered->value], true)) {
            return $user->hasPermission('handover_counter_orders');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    OrderStatus::Confirmed->value,
                    OrderStatus::Preparing->value,
                    OrderStatus::Ready->value,
                    OrderStatus::Dispatched->value,
                    OrderStatus::Delivered->value,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'الحالة الجديدة مطلوبة.',
            'status.in'       => 'الحالة المحددة غير صالحة.',
        ];
    }
}
