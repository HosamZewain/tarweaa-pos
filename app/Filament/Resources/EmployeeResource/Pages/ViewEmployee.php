<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeePenalty;
use App\Models\EmployeeSalary;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function resolveRecord(int | string $key): Employee
    {
        /** @var Employee $record */
        $record = parent::resolveRecord($key);

        return $record->loadMissing([
            'roles',
            'employeeProfile',
            'employeeSalaries',
            'employeePenalties',
            ...(\App\Support\HrFeature::hasAdvanceTables() ? ['employeeAdvances'] : []),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\EditAction::make(),
            Actions\Action::make('createSalary')
                ->label('إضافة راتب')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('create', EmployeeSalary::class) ?? false)
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('قيمة الراتب')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01')
                        ->prefix('ج.م')
                        ->required(),
                    Forms\Components\DatePicker::make('effective_from')
                        ->label('ساري من')
                        ->default(now()->toDateString())
                        ->required(),
                    Forms\Components\DatePicker::make('effective_to')
                        ->label('ساري حتى'),
                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    EmployeeSalary::query()->create([
                        'user_id' => $this->getRecord()->id,
                        'amount' => $data['amount'],
                        'effective_from' => $data['effective_from'],
                        'effective_to' => $data['effective_to'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Notification::make()
                        ->title('تمت إضافة الراتب')
                        ->success()
                        ->send();

                    $this->redirect(EmployeeResource::getUrl('view', ['record' => $this->getRecord()]), navigate: true);
                }),
            Actions\Action::make('createPenalty')
                ->label('إضافة جزاء')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('create', EmployeePenalty::class) ?? false)
                ->form([
                    Forms\Components\DatePicker::make('penalty_date')
                        ->label('تاريخ الجزاء')
                        ->default(now()->toDateString())
                        ->required(),
                    Forms\Components\TextInput::make('amount')
                        ->label('قيمة الجزاء')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01')
                        ->prefix('ج.م')
                        ->required(),
                    Forms\Components\TextInput::make('reason')
                        ->label('سبب الجزاء')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_active')
                        ->label('نشط')
                        ->default(true),
                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    EmployeePenalty::query()->create([
                        'user_id' => $this->getRecord()->id,
                        'penalty_date' => $data['penalty_date'],
                        'amount' => $data['amount'],
                        'reason' => $data['reason'],
                        'is_active' => (bool) ($data['is_active'] ?? true),
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Notification::make()
                        ->title('تمت إضافة الجزاء')
                        ->success()
                        ->send();

                    $this->redirect(EmployeeResource::getUrl('view', ['record' => $this->getRecord()]), navigate: true);
                }),
        ];

        if (\App\Support\HrFeature::hasAdvanceTables()) {
            $actions[] = Actions\Action::make('createAdvance')
                ->label('إضافة سلفة')
                ->icon('heroicon-o-wallet')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('create', EmployeeAdvance::class) ?? false)
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('قيمة السلفة')
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01')
                        ->prefix('ج.م')
                        ->required(),
                    Forms\Components\DatePicker::make('advance_date')
                        ->label('تاريخ السلفة')
                        ->default(now()->toDateString())
                        ->required(),
                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    EmployeeAdvance::query()->create([
                        'user_id' => $this->getRecord()->id,
                        'amount' => $data['amount'],
                        'advance_date' => $data['advance_date'],
                        'status' => 'active',
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Notification::make()
                        ->title('تمت إضافة السلفة')
                        ->success()
                        ->send();

                    $this->redirect(EmployeeResource::getUrl('view', ['record' => $this->getRecord()]), navigate: true);
                });
        }

        return $actions;
    }
}
