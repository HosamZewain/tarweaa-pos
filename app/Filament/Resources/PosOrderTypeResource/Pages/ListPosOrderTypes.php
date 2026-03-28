<?php

namespace App\Filament\Resources\PosOrderTypeResource\Pages;

use App\Filament\Resources\PosOrderTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosOrderTypes extends ListRecords
{
    protected static string $resource = PosOrderTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
