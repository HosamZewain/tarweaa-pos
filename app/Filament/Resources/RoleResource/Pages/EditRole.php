<?php
namespace App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permission_groups'] = RoleResource::getPermissionGroupState($this->getRecord());

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['permission_groups']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->getRecord()->permissions()->sync(
            RoleResource::extractPermissionIds($this->data ?? []),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => (auth()->user()?->can('delete', $this->getRecord()) ?? false) && $this->getRecord()->users()->doesntExist()),
        ];
    }
}
