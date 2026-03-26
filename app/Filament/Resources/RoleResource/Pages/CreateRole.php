<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['permission_groups']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->permissions()->sync(
            RoleResource::extractPermissionIds($this->data ?? []),
        );
    }
}
