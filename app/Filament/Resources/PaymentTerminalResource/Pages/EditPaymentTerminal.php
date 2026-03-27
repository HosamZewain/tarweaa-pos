<?php

namespace App\Filament\Resources\PaymentTerminalResource\Pages;

use App\Filament\Resources\PaymentTerminalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentTerminal extends EditRecord
{
    protected static string $resource = PaymentTerminalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PaymentTerminalResource::normalizeFeeData($data);
    }
}
