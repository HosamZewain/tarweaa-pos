<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use App\Services\RecipeService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMenuItem extends EditRecord
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }

    protected function afterSave(): void
    {
        app(RecipeService::class)->syncMenuItemCachedCost($this->record);
    }
}
