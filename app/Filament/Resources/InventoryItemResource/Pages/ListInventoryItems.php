<?php
namespace App\Filament\Resources\InventoryItemResource\Pages;
use App\Filament\Resources\InventoryItemResource;
use Filament\Resources\Pages\ListRecords;
class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}
