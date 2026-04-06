<?php

namespace App\Filament\Resources\InventoryTransferResource\Pages;

use App\Filament\Resources\InventoryTransferResource;
use App\Services\InventoryTransferService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInventoryTransfer extends EditRecord
{
    protected static string $resource = InventoryTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('اعتماد')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->visible(fn () => (auth()->user()?->can('update', $this->getRecord()) ?? false) && $this->record->isDraft() && !$this->record->approved_by)
                ->requiresConfirmation()
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update', $this->getRecord()), 403);

                    $this->record = app(InventoryTransferService::class)->approve($this->record, auth()->id());
                    Notification::make()->title('تم اعتماد التحويل')->success()->send();
                }),
            Actions\Action::make('send')
                ->label('إرسال التحويل')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => (auth()->user()?->can('update', $this->getRecord()) ?? false) && $this->record->isDraft())
                ->requiresConfirmation()
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update', $this->getRecord()), 403);

                    $this->record = app(InventoryTransferService::class)->send($this->record, auth()->id());
                    Notification::make()->title('تم إرسال التحويل')->success()->send();
                }),
            Actions\Action::make('receive')
                ->label('استلام التحويل')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn () => (auth()->user()?->can('update', $this->getRecord()) ?? false) && $this->record->isSent())
                ->requiresConfirmation()
                ->fillForm(function (): array {
                    $this->record->loadMissing('items.inventoryItem');

                    return [
                        'items' => $this->record->items->map(fn ($item) => [
                            'id' => $item->id,
                            'item_name' => $item->inventoryItem?->name ?? '—',
                            'quantity_sent' => (float) $item->quantity_sent,
                            'quantity_received' => (float) ($item->quantity_received > 0 ? $item->quantity_received : $item->quantity_sent),
                        ])->all(),
                    ];
                })
                ->form([
                    Forms\Components\Repeater::make('items')
                        ->label('كميات الاستلام')
                        ->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\TextInput::make('item_name')
                                ->label('المادة')
                                ->disabled()
                                ->dehydrated(false),
                            Forms\Components\TextInput::make('quantity_sent')
                                ->label('الكمية المرسلة')
                                ->disabled()
                                ->dehydrated(false),
                            Forms\Components\TextInput::make('quantity_received')
                                ->label('الكمية المستلمة')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->columns(3)
                        ->reorderable(false)
                        ->addable(false)
                        ->deletable(false),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can('update', $this->getRecord()), 403);

                    $items = $this->record->items()->get()->keyBy('id');

                    foreach ($data['items'] ?? [] as $row) {
                        $item = $items->get($row['id'] ?? null);

                        if (!$item) {
                            continue;
                        }

                        $item->update([
                            'quantity_received' => (float) ($row['quantity_received'] ?? 0),
                            'updated_by' => auth()->id(),
                        ]);
                    }

                    $this->record = app(InventoryTransferService::class)->receive($this->record, auth()->id());
                    Notification::make()->title('تم استلام التحويل')->success()->send();
                }),
            Actions\Action::make('cancel')
                ->label('إلغاء')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => (auth()->user()?->can('update', $this->getRecord()) ?? false) && $this->record->isDraft())
                ->requiresConfirmation()
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('update', $this->getRecord()), 403);

                    $this->record = app(InventoryTransferService::class)->cancel($this->record, auth()->id());
                    Notification::make()->title('تم إلغاء التحويل')->success()->send();
                }),
            Actions\DeleteAction::make()
                ->visible(fn () => (auth()->user()?->can('delete', $this->getRecord()) ?? false) && $this->record->isDraft()),
        ];
    }
}
