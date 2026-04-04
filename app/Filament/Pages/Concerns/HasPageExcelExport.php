<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Concerns\HasPrintPageAction;
use App\Services\AdminExcelExportService;
use Filament\Actions\Action;
use Illuminate\Support\Str;

trait HasPageExcelExport
{
    use HasPrintPageAction;

    protected function getHeaderActions(): array
    {
        return [
            $this->makePrintPageAction(),
            Action::make('exportExcel')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(AdminExcelExportService::class)->downloadFromData(
                    $this->getExcelExportFilename(),
                    $this->getExcelExportPayload(),
                )),
        ];
    }

    protected function getExcelExportFilename(): string
    {
        $baseName = method_exists($this, 'getTitle')
            ? (string) $this->getTitle()
            : class_basename(static::class);

        return Str::slug($baseName) . '-' . now()->format('Ymd-His') . '.xlsx';
    }

    protected function getExcelExportPayload(): array
    {
        if (property_exists($this, 'reportData') && is_array($this->reportData)) {
            return $this->reportData;
        }

        if (property_exists($this, 'valuation') && is_array($this->valuation)) {
            return ['valuation' => $this->valuation];
        }

        if (method_exists($this, 'getViewData')) {
            $viewData = $this->getViewData();

            if (is_array($viewData['reportData'] ?? null)) {
                return $viewData['reportData'];
            }

            return is_array($viewData) ? $viewData : [];
        }

        return [];
    }
}
