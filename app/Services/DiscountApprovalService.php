<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DiscountApprovalService
{
    private const CACHE_PREFIX = 'discount_approval:';
    private const TTL_MINUTES = 10;

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

    public function authorize(
        User $requestedBy,
        int $approverId,
        string $approverPin,
        string $type,
        float $value,
        string $reason,
    ): array
    {
        if (!$requestedBy->hasPermission('apply_discount')) {
            throw OrderException::discountPermissionRequired();
        }

        $approver = User::query()
            ->with('roles.permissions')
            ->find($approverId);

        if (!$approver || !$approver->canApproveDiscounts()) {
            throw OrderException::discountApproverInvalid();
        }

        if (!$approver->pin || !hash_equals((string) $approver->pin, trim($approverPin))) {
            throw OrderException::discountApproverPinInvalid();
        }

        $token = (string) Str::uuid();

        Cache::put(
            self::CACHE_PREFIX . $token,
            [
                'requested_by' => $requestedBy->id,
                'approver_id' => $approver->id,
                'type' => $type,
                'value' => round($value, 2),
                'reason' => trim($reason),
            ],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return [
            'token' => $token,
            'approver' => $approver,
            'expires_in_seconds' => self::TTL_MINUTES * 60,
        ];
    }

    public function consume(User $requestedBy, string $token, string $type, float $value, string $reason): User
    {
        $payload = Cache::pull(self::CACHE_PREFIX . $token);

        if (!$payload) {
            throw OrderException::discountApprovalRequired();
        }

        if (
            (int) ($payload['requested_by'] ?? 0) !== $requestedBy->id ||
            (string) ($payload['type'] ?? '') !== $type ||
            round((float) ($payload['value'] ?? 0), 2) !== round($value, 2) ||
            (string) ($payload['reason'] ?? '') !== trim($reason)
        ) {
            throw OrderException::discountApprovalRequired();
        }

        $approver = User::query()
            ->with('roles.permissions')
            ->find((int) $payload['approver_id']);

        if (!$approver || !$approver->canApproveDiscounts()) {
            throw OrderException::discountApproverInvalid();
        }

        return $approver;
    }
}
