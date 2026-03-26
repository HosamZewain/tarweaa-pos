<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'     => [
                'required',
                Rule::in([
                    InventoryTransactionType::Adjustment->value,
                    InventoryTransactionType::Waste->value,
                    InventoryTransactionType::Return->value,
                    InventoryTransactionType::TransferIn->value,
                    InventoryTransactionType::TransferOut->value,
                ]),
            ],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'notes'    => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'     => 'نوع الحركة مطلوب.',
            'type.in'           => 'نوع الحركة غير صالح.',
            'quantity.required' => 'الكمية مطلوبة.',
            'quantity.not_in'   => 'الكمية لا يمكن أن تكون صفراً.',
            'notes.required'    => 'الملاحظات مطلوبة.',
        ];
    }
}
