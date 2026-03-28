<?php

namespace App\Filament\Resources\UserMealBenefitProfileResource\Pages;

use App\Filament\Resources\UserMealBenefitProfileResource;
use App\Models\UserMealBenefitProfile;
use App\Services\UserMealBenefitProfileService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserMealBenefitProfile extends EditRecord
{
    protected static string $resource = UserMealBenefitProfileResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['benefit_mode'] = $this->getRecord()->benefitMode();

        if ($data['benefit_mode'] === 'mixed') {
            $data['benefit_mode'] = UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(UserMealBenefitProfileService::class)->normalizeForPersistence(
            $data,
            auth()->id(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
