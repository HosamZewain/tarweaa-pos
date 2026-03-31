<?php

namespace App\Filament\Resources\InventoryLocationResource\Pages;

use App\Filament\Resources\InventoryLocationResource;
use App\Models\InventoryLocation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateInventoryLocation extends CreateRecord
{
    protected static string $resource = InventoryLocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->guardDefaultIntegrity($data);

        if (!empty($data['is_default_purchase_destination'])) {
            InventoryLocation::query()->update(['is_default_purchase_destination' => false]);
        }

        if (!empty($data['is_default_recipe_deduction_location'])) {
            InventoryLocation::query()->update(['is_default_recipe_deduction_location' => false]);
        }

        return $data;
    }

    private function guardDefaultIntegrity(array $data): void
    {
        if (!($data['is_active'] ?? true)) {
            if (!empty($data['is_default_purchase_destination'])) {
                throw ValidationException::withMessages([
                    'data.is_active' => 'لا يمكن جعل موقع غير نشط هو الافتراضي لاستلام المشتريات.',
                ]);
            }

            if (!empty($data['is_default_recipe_deduction_location'])) {
                throw ValidationException::withMessages([
                    'data.is_active' => 'لا يمكن جعل موقع غير نشط هو الافتراضي لخصم الوصفات.',
                ]);
            }
        }
    }
}
