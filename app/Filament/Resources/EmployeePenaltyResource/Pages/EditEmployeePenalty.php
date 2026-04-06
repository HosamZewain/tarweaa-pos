<?php

namespace App\Filament\Resources\EmployeePenaltyResource\Pages;

use App\Filament\Resources\EmployeePenaltyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePenalty extends EditRecord
{
    protected static string $resource = EmployeePenaltyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }
}
