<?php

namespace App\Filament\Resources\PosOrderTypeResource\Pages;

use App\Filament\Resources\PosOrderTypeResource;
use App\Services\PosOrderTypeService;
use Filament\Resources\Pages\CreateRecord;

class CreatePosOrderType extends CreateRecord
{
    protected static string $resource = PosOrderTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(PosOrderTypeService::class)->normalizeForPersistence($data);
    }

    protected function afterCreate(): void
    {
        app(PosOrderTypeService::class)->syncDefaultState($this->record);
    }
}
