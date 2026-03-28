<?php

namespace App\Observers;

use App\Services\AdminActivityLogService;
use Illuminate\Database\Eloquent\Model;

class AdminActivityObserver
{
    public function __construct(
        private readonly AdminActivityLogService $activityLogService,
    ) {
    }

    public function created(Model $model): void
    {
        $this->activityLogService->logModelEvent(
            action: 'created',
            subject: $model,
            newValues: $this->activityLogService->extractModelSnapshot($model),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->activityLogService->extractChangedValues($model);

        if ($changes['old'] === [] && $changes['new'] === []) {
            return;
        }

        $this->activityLogService->logModelEvent(
            action: 'updated',
            subject: $model,
            oldValues: $changes['old'],
            newValues: $changes['new'],
        );
    }

    public function deleted(Model $model): void
    {
        $this->activityLogService->logModelEvent(
            action: 'deleted',
            subject: $model,
            oldValues: $this->activityLogService->extractModelSnapshot($model),
        );
    }

    public function restored(Model $model): void
    {
        $this->activityLogService->logModelEvent(
            action: 'restored',
            subject: $model,
            newValues: $this->activityLogService->extractModelSnapshot($model),
        );
    }
}
