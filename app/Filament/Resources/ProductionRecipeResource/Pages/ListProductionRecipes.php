<?php

namespace App\Filament\Resources\ProductionRecipeResource\Pages;

use App\Filament\Resources\ProductionRecipeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductionRecipes extends ListRecords
{
    protected static string $resource = ProductionRecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
