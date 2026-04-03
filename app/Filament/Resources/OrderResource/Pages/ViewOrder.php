<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\AdminActivityLogService;
use App\Services\OrderDeletionService;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function resolveRecord(int | string $key): Order
    {
        /** @var Order $record */
        $record = parent::resolveRecord($key);

        return $record->load([
            'shift',
            'drawerSession',
            'posDevice',
            'cashier',
            'customer',
            'refunder',
            'canceller',
            'items.modifiers',
            'payments',
            'orderDiscountLogs.appliedBy',
            'orderDiscountLogs.requestedBy',
            'latestOrderDiscountLog.appliedBy',
            'latestOrderDiscountLog.requestedBy',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::recordPaymentAction(),
            Actions\Action::make('safe_delete')
                ->label('حذف آمن')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('حذف الطلب مع عكس العمليات')
                ->modalDescription('سيتم عكس المخزون والمدفوعات النقدية والتسويات المرتبطة ثم إخفاء الطلب من النظام.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->label('سبب الحذف')->required(),
                ])
                ->visible(fn () => !$this->record->trashed() && !$this->record->hasNonCashPayments() && auth()->user()?->hasPermission('orders.delete'))
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->hasPermission('orders.delete'), 403);

                    app(AdminActivityLogService::class)->withoutModelLogging(function () use ($data): void {
                        app(OrderDeletionService::class)->deleteWithReversal($this->record, auth()->user(), $data['reason']);
                    });

                    app(AdminActivityLogService::class)->logAction(
                        action: 'deleted',
                        subject: $this->record,
                        description: 'تم حذف الطلب مع عكس العمليات من شاشة عرض الطلب.',
                        newValues: [
                            'status' => \App\Enums\OrderStatus::Cancelled->value,
                            'deletion_reason' => $data['reason'],
                            'deleted_at' => now(),
                        ],
                    );

                    $this->redirect(OrderResource::getUrl());
                }),
        ];
    }
}
