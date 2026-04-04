<?php

namespace App\Filament\Resources\ShiftResource\Pages;

use App\Filament\Concerns\HasPrintPageAction;
use App\Filament\Resources\ShiftResource;
use App\Models\Shift;
use App\Services\AdminExcelExportService;
use App\Support\BusinessTime;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    use HasPrintPageAction;

    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makePrintPageAction(),
            Action::make('exportExcel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(AdminExcelExportService::class)->downloadFromData(
                    'shifts-' . now()->format('Ymd-His') . '.xlsx',
                    ['shifts' => $this->getShiftsExportRows()],
                )),
        ];
    }

    private function getShiftsExportRows(): array
    {
        $timezone = BusinessTime::timezone();

        return $this->getFilteredSortedTableQuery()
            ->with(['opener:id,name', 'closer:id,name', 'drawerSessions:id,shift_id'])
            ->get()
            ->map(function (Shift $shift) use ($timezone): array {
                return [
                    'رقم الوردية' => $shift->shift_number,
                    'الحالة' => $shift->status->label(),
                    'فتح بواسطة' => $shift->opener?->name,
                    'أغلق بواسطة' => $shift->closer?->name,
                    'بداية الوردية' => $shift->started_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                    'نهاية الوردية' => $shift->ended_at?->timezone($timezone)->format('Y-m-d H:i:s'),
                    'النقد المتوقع' => $shift->expected_cash !== null ? (float) $shift->expected_cash : null,
                    'النقد الفعلي' => $shift->actual_cash !== null ? (float) $shift->actual_cash : null,
                    'فرق النقد' => $shift->cash_difference !== null ? (float) $shift->cash_difference : null,
                    'عدد الأدراج' => $shift->drawerSessions->count(),
                    'ملاحظات' => $shift->notes,
                ];
            })
            ->all();
    }
}
