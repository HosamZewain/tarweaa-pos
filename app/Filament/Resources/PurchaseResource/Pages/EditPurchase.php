<?php
namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('receiveFullPurchase')
                ->label('استلام كامل الشراء')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn () => $this->record->status !== 'cancelled' && $this->record->pendingItemsCount() > 0)
                ->requiresConfirmation()
                ->modalDescription('سيتم استلام كل الكميات المتبقية لكل بنود أمر الشراء في موقع الاستلام المحدد.')
                ->action(function (): void {
                    $receivedLines = $this->record->receiveAllPendingItems();
                    $this->record->refresh();

                    Notification::make()
                        ->title('تم استلام أمر الشراء')
                        ->body("تم استلام {$receivedLines} بند/بنود متبقية.")
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
