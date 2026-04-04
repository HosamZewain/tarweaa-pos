<?php

namespace App\Services;

use App\Enums\UserMealBenefitPeriodType;
use App\Models\UserMealBenefitProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserMealBenefitProfileService
{
    public function normalizeForPersistence(array $data, ?int $actorId = null, bool $isCreate = false): array
    {
        $mode = $data['benefit_mode'] ?? UserMealBenefitProfile::BENEFIT_MODE_NONE;

        $data['can_receive_owner_charge_orders'] = $mode === UserMealBenefitProfile::BENEFIT_MODE_OWNER_CHARGE;
        $data['monthly_allowance_enabled'] = $mode === UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE;
        $data['free_meal_enabled'] = $mode === UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL;
        $data['benefit_period_type'] = $data['benefit_period_type'] ?? UserMealBenefitPeriodType::Monthly->value;
        $data['monthly_allowance_amount'] = $data['monthly_allowance_enabled']
            ? round((float) ($data['monthly_allowance_amount'] ?? 0), 2)
            : 0;

        if (!$data['free_meal_enabled']) {
            $data['free_meal_type'] = null;
            $data['free_meal_monthly_count'] = null;
            $data['free_meal_monthly_amount'] = null;
        } elseif (($data['free_meal_type'] ?? null) === 'count') {
            $data['free_meal_monthly_amount'] = null;
        } elseif (($data['free_meal_type'] ?? null) === 'amount') {
            $data['free_meal_monthly_count'] = null;
        }

        if ($actorId) {
            if ($isCreate) {
                $data['created_by'] = $actorId;
            }

            $data['updated_by'] = $actorId;
        }

        unset($data['benefit_mode']);

        return $data;
    }

    public function upsertForUsers(array $userIds, array $data, ?int $actorId = null): int
    {
        $userIds = collect($userIds)
            ->filter()
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values()
            ->all();

        $normalizedData = $this->normalizeForPersistence($data, $actorId);
        $allowedMenuItems = array_values(array_map('intval', Arr::wrap($data['allowedMenuItems'] ?? [])));

        return DB::transaction(function () use ($userIds, $normalizedData, $allowedMenuItems, $actorId): int {
            foreach ($userIds as $userId) {
                $profile = UserMealBenefitProfile::query()->firstOrNew([
                    'user_id' => $userId,
                ]);

                if (!$profile->exists && $actorId) {
                    $profile->created_by = $actorId;
                }

                $profile->fill([
                    ...$normalizedData,
                    'user_id' => $userId,
                ]);

                $profile->save();

                $profile->allowedMenuItems()->sync(
                    $profile->free_meal_enabled ? $allowedMenuItems : [],
                );
            }

            return count($userIds);
        });
    }
}
