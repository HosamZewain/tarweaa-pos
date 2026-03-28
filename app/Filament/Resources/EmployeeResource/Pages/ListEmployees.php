<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('rolesScopeNotice')
                ->label('الأدوار هنا تشغيلية فقط: موظف، كاشير، مطبخ، كاونتر')
                ->icon('heroicon-o-information-circle')
                ->color('gray')
                ->disabled()
                ->extraAttributes(['class' => 'pointer-events-none']),
            Actions\CreateAction::make()->label('إضافة موظف'),
        ];
    }
}
