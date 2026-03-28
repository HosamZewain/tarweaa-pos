<?php

namespace App\Filament\Resources\UserMealBenefitProfileResource\Pages;

use App\Filament\Resources\UserMealBenefitProfileResource;
use App\Services\UserMealBenefitProfileService;
use Filament\Resources\Pages\CreateRecord;

class CreateUserMealBenefitProfile extends CreateRecord
{
    protected static string $resource = UserMealBenefitProfileResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(UserMealBenefitProfileService::class)->normalizeForPersistence(
            $data,
            auth()->id(),
            isCreate: true,
        );
    }
}
