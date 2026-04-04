<?php

namespace App\Filament\Resources\ProductionRecipeResource\Pages;

use App\Filament\Resources\ProductionRecipeResource;
use App\Models\InventoryItem;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionRecipe extends CreateRecord
{
    protected static string $resource = ProductionRecipeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!filled($data['name'] ?? null) && !empty($data['prepared_item_id'])) {
            $preparedItem = InventoryItem::query()->find($data['prepared_item_id']);

            if ($preparedItem) {
                $data['name'] = 'وصفة إنتاج ' . $preparedItem->name;
            }
        }

        return $data;
    }
}
