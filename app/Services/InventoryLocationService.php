<?php

namespace App\Services;

use App\Models\InventoryLocation;
use App\Support\ProductionFeature;

class InventoryLocationService
{
    public function defaultPurchaseDestination(): ?InventoryLocation
    {
        return InventoryLocation::query()
            ->where('is_default_purchase_destination', true)
            ->where('is_active', true)
            ->first();
    }

    public function defaultRecipeDeductionLocation(): ?InventoryLocation
    {
        return InventoryLocation::query()
            ->where('is_default_recipe_deduction_location', true)
            ->where('is_active', true)
            ->first();
    }

    public function warehouse(): ?InventoryLocation
    {
        return InventoryLocation::query()
            ->where('type', 'warehouse')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    public function restaurant(): ?InventoryLocation
    {
        return InventoryLocation::query()
            ->where('type', 'restaurant')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    public function defaultProductionLocation(): ?InventoryLocation
    {
        if (!ProductionFeature::hasProductionLocationFlag()) {
            return $this->defaultRecipeDeductionLocation()
                ?? $this->restaurant();
        }

        return InventoryLocation::query()
            ->where('is_active', true)
            ->where('is_default_production_location', true)
            ->orderBy('id')
            ->first()
            ?? $this->defaultRecipeDeductionLocation()
            ?? $this->restaurant();
    }
}
