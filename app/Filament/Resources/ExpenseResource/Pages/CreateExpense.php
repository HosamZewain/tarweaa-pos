<?php
namespace App\Filament\Resources\ExpenseResource\Pages;
use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;
class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        return $data;
    }
}
