<?php

namespace App\Filament\Resources\ProductionBatchResource\Pages;

use App\Filament\Resources\ProductionBatchResource;
use App\Services\ManagerVerificationService;
use App\Services\ProductionBatchService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewProductionBatch extends ViewRecord
{
    protected static string $resource = ProductionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('دفعة جديدة')
                ->url(ProductionBatchResource::getUrl('create')),
            Actions\Action::make('voidBatch')
                ->label('إلغاء الدفعة')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => (auth()->user()?->can('update', $this->getRecord()) ?? false) && $this->record->canBeVoided())
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('سبب الإلغاء')
                        ->required(),
                    Forms\Components\Select::make('approver_id')
                        ->label('اعتماد بواسطة')
                        ->options(fn () => ProductionBatchResource::approverOptions())
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('approver_pin')
                        ->label('PIN المعتمد')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $approverId = (int) ($data['approver_id'] ?? 0);
                    $approverPin = (string) ($data['approver_pin'] ?? '');
                    $approver = app(ManagerVerificationService::class)->findApprover($approverId);

                    if (!$approver) {
                        throw ValidationException::withMessages([
                            'data.approver_id' => 'المعتمد المحدد غير صالح.',
                        ]);
                    }

                    if (!app(ManagerVerificationService::class)->verifyPin($approver, $approverPin)) {
                        throw ValidationException::withMessages([
                            'data.approver_pin' => 'PIN المعتمد غير صحيح.',
                        ]);
                    }

                    $this->record = app(ProductionBatchService::class)->void(
                        batch: $this->record,
                        actorId: auth()->id(),
                        reason: (string) $data['reason'],
                        approvedBy: $approver->id,
                    );
                }),
        ];
    }
}
