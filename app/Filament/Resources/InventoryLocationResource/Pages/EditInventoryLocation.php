<?php

namespace App\Filament\Resources\InventoryLocationResource\Pages;

use App\Filament\Resources\InventoryLocationResource;
use App\Models\InventoryLocation;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditInventoryLocation extends EditRecord
{
    protected static string $resource = InventoryLocationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->guardDefaultIntegrity($data);

        if (!empty($data['is_default_purchase_destination'])) {
            InventoryLocation::query()
                ->whereKeyNot($this->record->getKey())
                ->update(['is_default_purchase_destination' => false]);
        }

        if (!empty($data['is_default_recipe_deduction_location'])) {
            InventoryLocation::query()
                ->whereKeyNot($this->record->getKey())
                ->update(['is_default_recipe_deduction_location' => false]);
        }

        if (!empty($data['is_default_production_location'])) {
            InventoryLocation::query()
                ->whereKeyNot($this->record->getKey())
                ->update(['is_default_production_location' => false]);
        }

        return $data;
    }

    private function guardDefaultIntegrity(array $data): void
    {
        $isActive = (bool) ($data['is_active'] ?? false);
        $isPurchaseDefault = (bool) ($data['is_default_purchase_destination'] ?? false);
        $isRecipeDefault = (bool) ($data['is_default_recipe_deduction_location'] ?? false);
        $isProductionDefault = (bool) ($data['is_default_production_location'] ?? false);
        $messages = [];

        if (!$isActive && ($isPurchaseDefault || $isRecipeDefault || $isProductionDefault)) {
            $messages['data.is_active'] = 'لا يمكن تعطيل موقع مخزني ما زال محددًا كموقع افتراضي.';
        }

        if ($this->record->is_default_purchase_destination && !($isActive && $isPurchaseDefault)) {
            $hasAlternative = InventoryLocation::query()
                ->whereKeyNot($this->record->getKey())
                ->where('is_active', true)
                ->where('is_default_purchase_destination', true)
                ->exists();

            if (!$hasAlternative) {
                $messages['data.is_default_purchase_destination'] = 'يجب أن يبقى هناك موقع نشط افتراضي لاستلام المشتريات.';
            }
        }

        if ($this->record->is_default_recipe_deduction_location && !($isActive && $isRecipeDefault)) {
            $hasAlternative = InventoryLocation::query()
                ->whereKeyNot($this->record->getKey())
                ->where('is_active', true)
                ->where('is_default_recipe_deduction_location', true)
                ->exists();

            if (!$hasAlternative) {
                $messages['data.is_default_recipe_deduction_location'] = 'يجب أن يبقى هناك موقع نشط افتراضي لخصم الوصفات.';
            }
        }

        if ($this->record->is_default_production_location && !($isActive && $isProductionDefault)) {
            $hasAlternative = InventoryLocation::query()
                ->whereKeyNot($this->record->getKey())
                ->where('is_active', true)
                ->where('is_default_production_location', true)
                ->exists();

            if (!$hasAlternative) {
                $messages['data.is_default_production_location'] = 'يجب أن يبقى هناك موقع نشط افتراضي للإنتاج والتحضير.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }
}
