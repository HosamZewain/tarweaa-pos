<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Concerns\HasPrintPageAction;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\AdminExcelExportService;
use App\Support\BusinessTime;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    use HasPrintPageAction;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makePrintPageAction(),
            Action::make('exportExcel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(AdminExcelExportService::class)->downloadFromData(
                    'orders-' . now()->format('Ymd-His') . '.xlsx',
                    ['orders' => $this->getOrdersExportRows()],
                )),
        ];
    }

    private function getOrdersExportRows(): array
    {
        $timezone = BusinessTime::timezone();

        return $this->getFilteredSortedTableQuery()
            ->with([
                'cashier:id,name',
                'shift:id,shift_number',
                'drawerSession:id,session_number',
                'payments',
                'settlement',
            ])
            ->get()
            ->map(function (Order $order) use ($timezone): array {
                return [
                    'رقم الطلب' => $order->order_number,
                    'النوع' => $order->type->label(),
                    'الحالة' => $order->status->label(),
                    'المصدر' => $order->source->label(),
                    'الكاشير' => $order->cashier?->name,
                    'الوردية' => $order->shift?->shift_number,
                    'جلسة الدرج' => $order->drawerSession?->session_number,
                    'العميل' => $order->customer_name,
                    'الإجمالي' => (float) $order->total,
                    'المدفوع' => (float) $order->reportablePaidAmount(),
                    'الباقي' => (float) $order->change_amount,
                    'الخصم' => (float) $order->discount_amount,
                    'حالة الدفع' => $order->payment_status->label(),
                    'طرق الدفع' => collect($order->reportablePaymentBreakdown())
                        ->map(fn ($amount, $method) => $method . ':' . number_format((float) $amount, 2, '.', ''))
                        ->implode(' | '),
                    'وقت الإنشاء' => $order->created_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                ];
            })
            ->all();
    }
}
