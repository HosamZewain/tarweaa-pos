<?php

namespace App\Filament\Widgets\Concerns;

trait InteractsWithDashboardAnalyticsVisibility
{
    public static function canView(): bool
    {
        return auth()->user()?->canViewDashboardAnalytics() ?? false;
    }
}
