<?php

namespace App\Filament\Resources\ProductionBatchResource\Pages;

use App\Filament\Resources\ProductionBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductionBatches extends ListRecords
{
    protected static string $resource = ProductionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
