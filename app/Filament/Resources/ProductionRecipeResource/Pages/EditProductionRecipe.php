<?php

namespace App\Filament\Resources\ProductionRecipeResource\Pages;

use App\Filament\Resources\ProductionRecipeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductionRecipe extends EditRecord
{
    protected static string $resource = ProductionRecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
