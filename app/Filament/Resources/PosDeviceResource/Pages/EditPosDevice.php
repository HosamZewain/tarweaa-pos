<?php
namespace App\Filament\Resources\PosDeviceResource\Pages;
use App\Filament\Resources\PosDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditPosDevice extends EditRecord
{
    protected static string $resource = PosDeviceResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
