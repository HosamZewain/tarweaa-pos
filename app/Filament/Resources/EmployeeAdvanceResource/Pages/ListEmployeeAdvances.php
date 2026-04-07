<?php

namespace App\Filament\Resources\EmployeeAdvanceResource\Pages;

use App\Filament\Resources\EmployeeAdvanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeAdvances extends ListRecords
{
    protected static string $resource = EmployeeAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('إضافة سلفة'),
        ];
    }
}
