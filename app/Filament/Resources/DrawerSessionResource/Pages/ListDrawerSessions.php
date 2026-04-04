<?php

namespace App\Filament\Resources\DrawerSessionResource\Pages;

use App\Filament\Concerns\HasPrintPageAction;
use App\Filament\Resources\DrawerSessionResource;
use App\Models\CashierDrawerSession;
use App\Services\AdminExcelExportService;
use App\Support\BusinessTime;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListDrawerSessions extends ListRecords
{
    use HasPrintPageAction;

    protected static string $resource = DrawerSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makePrintPageAction(),
            Action::make('exportExcel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(AdminExcelExportService::class)->downloadFromData(
                    'drawer-sessions-' . now()->format('Ymd-His') . '.xlsx',
                    ['drawer_sessions' => $this->getDrawerSessionsExportRows()],
                )),
        ];
    }

    private function getDrawerSessionsExportRows(): array
    {
        $timezone = BusinessTime::timezone();

        return $this->getFilteredSortedTableQuery()
            ->with([
                'cashier:id,name',
                'shift:id,shift_number',
                'posDevice:id,name',
            ])
            ->get()
            ->map(function (CashierDrawerSession $session) use ($timezone): array {
                return [
                    'رقم الجلسة' => $session->session_number,
                    'الكاشير' => $session->cashier?->name,
                    'الوردية' => $session->shift?->shift_number,
                    'الجهاز' => $session->posDevice?->name,
                    'الحالة' => $session->status->label(),
                    'رصيد الفتح' => (float) $session->opening_balance,
                    'الرصيد المتوقع' => (float) $session->calculateExpectedBalance(),
                    'الرصيد الفعلي' => $session->closing_balance !== null ? (float) $session->closing_balance : null,
                    'فرق الجرد' => $session->cash_difference !== null ? (float) $session->cash_difference : null,
                    'وقت البداية' => $session->started_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                    'وقت النهاية' => $session->ended_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                    'ملاحظات' => $session->notes,
                ];
            })
            ->all();
    }
}
