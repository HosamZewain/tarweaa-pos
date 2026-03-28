<?php

namespace App\Http\Requests\Counter;

use Illuminate\Foundation\Http\FormRequest;

class HandoverCounterOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('handover_counter_orders');
    }

    public function rules(): array
    {
        return [];
    }
}
