<?php

namespace App\Filament\Resources\InventoryTransferResource\Pages;

use App\Filament\Resources\InventoryTransferResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryTransfer extends CreateRecord
{
    protected static string $resource = InventoryTransferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';

        return $data;
    }
}
