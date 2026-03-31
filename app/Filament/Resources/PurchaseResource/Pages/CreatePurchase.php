<?php
namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    /** @var array<int, array<string, mixed>> */
    private array $initialItems = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->initialItems = array_values(array_filter($data['items_payload'] ?? [], function (array $item): bool {
            return !empty($item['inventory_item_id']) && (float) ($item['quantity_ordered'] ?? 0) > 0;
        }));

        unset($data['items_payload']);

        if (($data['status'] ?? null) === 'received' && $this->initialItems === []) {
            throw ValidationException::withMessages([
                'data.items_payload' => 'يجب إضافة بند واحد على الأقل إذا كانت حالة أمر الشراء "مستلم".',
            ]);
        }

        if ($this->initialItems !== []) {
            $subtotal = collect($this->initialItems)->sum(function (array $item): float {
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $quantity = (float) ($item['quantity_ordered'] ?? 0);

                return round($unitPrice * $quantity, 2);
            });

            $data['subtotal'] = round($subtotal, 2);

            if (!isset($data['total']) || (float) $data['total'] === 0.0) {
                $data['total'] = max(0, (float) $data['subtotal'] - (float) ($data['discount_amount'] ?? 0) + (float) ($data['tax_amount'] ?? 0));
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Purchase
    {
        return DB::transaction(function () use ($data): Purchase {
            /** @var Purchase $purchase */
            $purchase = static::getModel()::create($data);

            foreach ($this->initialItems as $itemData) {
                $unitPrice = (float) ($itemData['unit_price'] ?? 0);
                $quantityOrdered = (float) ($itemData['quantity_ordered'] ?? 0);

                $purchase->items()->create([
                    'inventory_item_id' => $itemData['inventory_item_id'],
                    'unit' => $itemData['unit'] ?? null,
                    'unit_price' => $unitPrice,
                    'quantity_ordered' => $quantityOrdered,
                    'quantity_received' => 0,
                    'total' => round($unitPrice * $quantityOrdered, 2),
                    'notes' => $itemData['notes'] ?? null,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }

            if ($this->initialItems !== []) {
                $purchase->recalculate();
            }

            if (($data['status'] ?? null) === 'received' && $this->initialItems !== []) {
                $purchase->receiveAllPendingItems();
                $purchase->refresh();
            }

            return $purchase;
        });
    }
}
