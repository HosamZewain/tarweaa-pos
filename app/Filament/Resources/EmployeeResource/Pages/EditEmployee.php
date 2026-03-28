<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Services\EmployeeManagementService;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected ?string $staffRole = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['staff_role'] = app(EmployeeManagementService::class)->primaryAssignableRoleName($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->staffRole = $data['staff_role'] ?? null;
        unset($data['staff_role']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(EmployeeManagementService::class)->syncOperationalRole($this->record, $this->staffRole);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
