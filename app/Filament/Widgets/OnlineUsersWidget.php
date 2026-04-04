<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\BusinessTime;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OnlineUsersWidget extends Widget
{
    protected static ?int $sort = 99;

    protected string $view = 'filament.widgets.online-users-widget';

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $now = BusinessTime::now();
        $cutoffTimestamp = $now->copy()->subHour()->timestamp;

        $sessionRows = DB::table('sessions')
            ->selectRaw('user_id, MAX(last_activity) as last_activity')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $cutoffTimestamp)
            ->groupBy('user_id')
            ->orderByDesc('last_activity')
            ->get();

        $users = User::query()
            ->with(['roles:id,name,display_name', 'activeDrawerSession:id,cashier_id,session_number'])
            ->whereIn('id', $sessionRows->pluck('user_id')->all())
            ->get()
            ->keyBy('id');

        $onlineUsers = $sessionRows
            ->map(fn (object $row): ?array => $this->mapOnlineUserRow($row, $users, $now))
            ->filter()
            ->values();

        return [
            'onlineUsers' => $onlineUsers,
            'summary' => $this->buildSummary($onlineUsers),
        ];
    }

    protected function mapOnlineUserRow(object $row, Collection $users, Carbon $now): ?array
    {
        /** @var \App\Models\User|null $user */
        $user = $users->get((int) $row->user_id);

        if (!$user || !$user->is_active) {
            return null;
        }

        $lastActivityAt = Carbon::createFromTimestamp((int) $row->last_activity)
            ->timezone($now->timezone);

        $minutesAgo = max(0, (int) floor($lastActivityAt->diffInMinutes($now, true)));
        $roleNames = $user->roles->pluck('name');
        $roleLabel = $user->roles->pluck('display_name')->filter()->first()
            ?? $user->roles->pluck('name')->first()
            ?? 'بدون دور';

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role_label' => $roleLabel,
            'role_names' => $roleNames->all(),
            'last_activity_at' => $lastActivityAt,
            'last_activity_label' => $minutesAgo === 0 ? 'الآن' : "منذ {$minutesAgo} دقيقة",
            'minutes_ago' => $minutesAgo,
            'status_label' => $minutesAgo <= 5 ? 'نشط الآن' : 'متصل',
            'status_tone' => $minutesAgo <= 5 ? 'success' : 'info',
            'drawer_session_number' => $user->activeDrawerSession?->session_number,
        ];
    }

    protected function buildSummary(Collection $onlineUsers): array
    {
        return [
            'total' => $onlineUsers->count(),
            'recently_active' => $onlineUsers->where('minutes_ago', '<=', 5)->count(),
            'privileged' => $onlineUsers->filter(fn (array $user): bool => collect($user['role_names'])->intersect(User::privilegedRoleNames())->isNotEmpty())->count(),
            'operational' => $onlineUsers->filter(fn (array $user): bool => collect($user['role_names'])->intersect(['cashier', 'counter', 'kitchen', 'inventory', 'employee'])->isNotEmpty())->count(),
        ];
    }
}
