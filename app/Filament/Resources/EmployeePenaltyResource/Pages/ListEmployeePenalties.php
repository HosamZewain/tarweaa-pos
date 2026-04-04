<?php

namespace App\Filament\Resources\EmployeePenaltyResource\Pages;

use App\Filament\Resources\EmployeePenaltyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePenalties extends ListRecords
{
    protected static string $resource = EmployeePenaltyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
