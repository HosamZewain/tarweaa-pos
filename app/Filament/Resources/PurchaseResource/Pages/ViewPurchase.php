<?php
namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('receiveFullPurchase')
                ->label('استلام كامل الشراء')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn () => (auth()->user()?->can('update', $this->getRecord()) ?? false) && $this->record->status !== 'cancelled' && $this->record->pendingItemsCount() > 0)
                ->requiresConfirmation()
                ->modalDescription('سيتم استلام كل الكميات المتبقية لكل بنود أمر الشراء في موقع الاستلام المحدد.')
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update', $this->getRecord()), 403);

                    $receivedLines = $this->record->receiveAllPendingItems();
                    $this->record->refresh();

                    Notification::make()
                        ->title('تم استلام أمر الشراء')
                        ->body("تم استلام {$receivedLines} بند/بنود متبقية.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
