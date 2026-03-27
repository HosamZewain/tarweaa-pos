<?php

namespace App\Filament\Resources\PaymentTerminalResource\Pages;

use App\Filament\Resources\PaymentTerminalResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentTerminal extends CreateRecord
{
    protected static string $resource = PaymentTerminalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PaymentTerminalResource::normalizeFeeData($data);
    }
}
