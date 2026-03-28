<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Services\EmployeeManagementService;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected ?string $staffRole = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->staffRole = $data['staff_role'] ?? null;
        unset($data['staff_role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        app(EmployeeManagementService::class)->syncOperationalRole($this->record, $this->staffRole);
    }
}
