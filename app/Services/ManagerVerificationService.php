<?php

namespace App\Services;

use App\Models\User;

class ManagerVerificationService
{
    public function listApprovers(): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'manager']))
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ])
            ->all();
    }

    public function findApprover(int $approverId): ?User
    {
        $user = User::query()
            ->with('roles.permissions')
            ->find($approverId);

        if (!$user || !$user->canApproveSensitivePosActions()) {
            return null;
        }

        return $user;
    }

    public function verifyPin(User $approver, string $pin): bool
    {
        return filled($approver->pin) && hash_equals((string) $approver->pin, trim($pin));
    }
}
