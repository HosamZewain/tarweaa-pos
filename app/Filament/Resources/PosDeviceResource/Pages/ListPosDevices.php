<?php
namespace App\Filament\Resources\PosDeviceResource\Pages;
use App\Filament\Resources\PosDeviceResource;
use Filament\Resources\Pages\ListRecords;
class ListPosDevices extends ListRecords
{
    protected static string $resource = PosDeviceResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}
