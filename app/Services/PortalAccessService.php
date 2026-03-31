<?php

namespace App\Services;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Session\Session;

class PortalAccessService
{
    private const SESSION_FRONTEND_TOKEN = 'portal_frontend_token';

    public function getLauncherEntries(User $user): array
    {
        $entries = [];

        if ($user->canAccessPosSurface()) {
            $entries[] = [
                'key' => 'pos',
                'title' => 'نقطة البيع',
                'description' => 'فتح الدرج والعمل على الطلبات والدفع.',
                'url' => '/pos/drawer',
                'group' => 'التشغيل',
                'icon' => 'POS',
                'tone' => 'accent',
            ];
        }

        if ($user->canAccessKitchenSurface()) {
            $entries[] = [
                'key' => 'kitchen',
                'title' => 'المطبخ',
                'description' => 'متابعة الطلبات الجاهزة والتحضير داخل المطبخ.',
                'url' => '/kitchen',
                'group' => 'التشغيل',
                'icon' => 'KIT',
                'tone' => 'success',
            ];
        }

        if ($user->canAccessCounterSurface()) {
            $entries[] = [
                'key' => 'counter_all',
                'title' => 'الكاونتر',
                'description' => 'عرض كل الطلبات الجاهزة للتسليم.',
                'url' => '/counter',
                'group' => 'التشغيل',
                'icon' => 'ALL',
                'tone' => 'info',
            ];
            $entries[] = [
                'key' => 'counter_odd',
                'title' => 'الكاونتر الفردي',
                'description' => 'عرض الطلبات الفردية فقط.',
                'url' => '/counter/odd',
                'group' => 'التشغيل',
                'icon' => 'ODD',
                'tone' => 'warning',
            ];
            $entries[] = [
                'key' => 'counter_even',
                'title' => 'الكاونتر الزوجي',
                'description' => 'عرض الطلبات الزوجية فقط.',
                'url' => '/counter/even',
                'group' => 'التشغيل',
                'icon' => 'EVN',
                'tone' => 'warning',
            ];
        }

        if ($this->canAccessAdmin($user)) {
            $entries[] = [
                'key' => 'admin',
                'title' => 'لوحة الإدارة',
                'description' => 'إدارة النظام والتقارير والإعدادات.',
                'url' => '/admin',
                'group' => 'الإدارة',
                'icon' => 'ADM',
                'tone' => 'danger',
            ];
        }

        return $entries;
    }

    public function hasAnyAccess(User $user): bool
    {
        return $this->getLauncherEntries($user) !== [];
    }

    public function canAccessAdmin(User $user): bool
    {
        $panel = Filament::getPanel('admin', isStrict: false);

        return $panel !== null && $user->canAccessPanel($panel);
    }

    public function canAccessSurface(User $user, string $surface): bool
    {
        return match ($surface) {
            'pos' => $user->canAccessPosSurface(),
            'kitchen' => $user->canAccessKitchenSurface(),
            'counter' => $user->canAccessCounterSurface(),
            'admin' => $this->canAccessAdmin($user),
            default => false,
        };
    }

    public function resolveHome(User $user): string
    {
        $entries = $this->getLauncherEntries($user);

        if (count($entries) === 1) {
            return $entries[0]['url'];
        }

        return route('portal.launcher');
    }

    public function resolveRedirectTarget(User $user, ?string $target): string
    {
        $target = $this->sanitizeRedirectTarget($target);

        if ($target !== null && $this->isAllowedRedirectTarget($user, $target)) {
            return $target;
        }

        return $this->resolveHome($user);
    }

    public function sanitizeRedirectTarget(?string $target): ?string
    {
        if (!is_string($target) || $target === '') {
            return null;
        }

        if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return null;
        }

        return $target;
    }

    public function getFrontendBootstrapPayload(User $user, Session $session): array
    {
        $plainTextToken = $session->get(self::SESSION_FRONTEND_TOKEN);

        if (!is_string($plainTextToken) || !str_contains($plainTextToken, '|')) {
            $plainTextToken = $user->createToken('portal-web-session')->plainTextToken;
            $session->put(self::SESSION_FRONTEND_TOKEN, $plainTextToken);
        }

        return [
            'token' => $plainTextToken,
            'user' => $this->formatUser($user),
        ];
    }

    public function revokeFrontendBootstrapPayload(?User $user, Session $session): void
    {
        $plainTextToken = $session->pull(self::SESSION_FRONTEND_TOKEN);

        if (!$user || !is_string($plainTextToken) || !str_contains($plainTextToken, '|')) {
            return;
        }

        [$tokenId] = explode('|', $plainTextToken, 2);

        if ($tokenId !== '') {
            $user->tokens()->whereKey((int) $tokenId)->delete();
        }
    }

    public function formatUser(User $user): array
    {
        $user->loadMissing('roles.permissions');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'can_access_pos' => $user->canAccessPosSurface(),
            'can_access_kitchen' => $user->canAccessKitchenSurface(),
            'can_access_counter' => $user->canAccessCounterSurface(),
            'can_access_admin' => $this->canAccessAdmin($user),
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->values()->all(),
            'permissions' => $user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function isAllowedRedirectTarget(User $user, string $target): bool
    {
        if (str_starts_with($target, '/pos')) {
            return $user->canAccessPosSurface();
        }

        if (str_starts_with($target, '/kitchen')) {
            return $user->canAccessKitchenSurface();
        }

        if (str_starts_with($target, '/counter') || str_starts_with($target, '/counter-screen')) {
            return $user->canAccessCounterSurface();
        }

        if (str_starts_with($target, '/admin')) {
            return $this->canAccessAdmin($user);
        }

        if (str_starts_with($target, '/launcher') || $target === '/') {
            return $this->hasAnyAccess($user);
        }

        return false;
    }
}
