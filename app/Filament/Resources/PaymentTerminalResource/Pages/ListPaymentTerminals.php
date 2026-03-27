<?php

namespace App\Filament\Resources\PaymentTerminalResource\Pages;

use App\Filament\Resources\PaymentTerminalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentTerminals extends ListRecords
{
    protected static string $resource = PaymentTerminalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
