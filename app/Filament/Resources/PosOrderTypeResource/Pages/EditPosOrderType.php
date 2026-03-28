<?php

namespace App\Filament\Resources\PosOrderTypeResource\Pages;

use App\Filament\Resources\PosOrderTypeResource;
use App\Services\PosOrderTypeService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosOrderType extends EditRecord
{
    protected static string $resource = PosOrderTypeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(PosOrderTypeService::class)->normalizeForPersistence($data);
    }

    protected function afterSave(): void
    {
        app(PosOrderTypeService::class)->syncDefaultState($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('أرشفة')
                ->after(fn () => app(PosOrderTypeService::class)->ensureDefaultExists()),
            Actions\RestoreAction::make()
                ->after(fn () => app(PosOrderTypeService::class)->ensureDefaultExists()),
        ];
    }
}
