<?php

namespace App\Filament\Pages\Concerns;

trait HasPagePermission
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasPermission(static::$permissionName) ?? false;
    }
}
