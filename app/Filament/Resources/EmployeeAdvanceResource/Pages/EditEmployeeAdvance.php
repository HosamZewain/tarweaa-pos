<?php

namespace App\Filament\Resources\EmployeeAdvanceResource\Pages;

use App\Filament\Resources\EmployeeAdvanceResource;
use App\Services\EmployeeAdvanceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeAdvance extends EditRecord
{
    protected static string $resource = EmployeeAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancelAdvance')
                ->label('إلغاء السلفة')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => auth()->user()?->can('cancel', $this->getRecord()) ?? false)
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('سبب الإلغاء')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can('cancel', $this->getRecord()), 403);
                    app(EmployeeAdvanceService::class)->cancel($this->getRecord(), $data['reason'] ?? null);
                    $this->refreshFormData(['status', 'cancellation_reason', 'cancelled_at', 'cancelled_by']);
                }),
        ];
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        abort_unless(auth()->user()?->can('view', $this->getRecord()), 403);
    }
}
