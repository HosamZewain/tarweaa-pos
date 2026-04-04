<?php

namespace App\Services;

use App\DTOs\CashMovementData;
use App\DTOs\CloseDrawerData;
use App\DTOs\OpenDrawerData;
use App\Enums\CashMovementType;
use App\Enums\DrawerSessionStatus;
use App\Exceptions\DrawerException;
use App\Exceptions\ShiftException;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\CashMovement;
use App\Models\PosDevice;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DrawerSessionService
{
    private const CLOSE_PREVIEW_CACHE_PREFIX = 'drawer_close_preview:';
    private const CLOSE_RECONCILIATION_LOCK_PREFIX = 'drawer_close_reconciliation:';
    private const CLOSE_PREVIEW_TTL_MINUTES = 10;

    // ─────────────────────────────────────────
    // Open Drawer
    // ─────────────────────────────────────────

    /**
     * Open a drawer session for a cashier.
     *
     * Rules enforced:
     *  1. The referenced shift must be open
     *  2. The cashier must NOT already have an active session
     *     (guarded at DB level by cashier_active_sessions PK)
     *  3. POS device must be active
     *  4. Opening balance is recorded as a cash movement
     *
     * @throws DrawerException
     * @throws ShiftException
     */
    public function open(User $actor, OpenDrawerData $data): CashierDrawerSession
    {
        $this->authoriseOpen($actor, $data);

        // 1. Verify shift is open
        $shift = Shift::findOrFail($data->shiftId);

        if (!$shift->isOpen()) {
            throw DrawerException::shiftNotOpen();
        }

        // 2. Check in-memory guard (cheaper than letting DB throw)
        if (CashierActiveSession::find($data->cashierId)) {
            $cashierName = User::findOrFail($data->cashierId)->name;
            throw DrawerException::alreadyOpen($cashierName);
        }

        // 3. Verify POS device is active (no abort_if — use domain exception)
        $device = PosDevice::findOrFail($data->posDeviceId);
        if (!$device->is_active) {
            throw DrawerException::deviceInactive($device->name);
        }

        // 4. Create session + guard + opening movement in a single transaction
        try {
            $session = DB::transaction(function () use ($data) {
                $session = CashierDrawerSession::create([
                    'session_number'  => CashierDrawerSession::generateSessionNumber(),
                    'cashier_id'      => $data->cashierId,
                    'shift_id'        => $data->shiftId,
                    'pos_device_id'   => $data->posDeviceId,
                    'opened_by'       => $data->openedBy,
                    'opening_balance' => $data->openingBalance,
                    'status'          => DrawerSessionStatus::Open,
                    'started_at'      => now(),
                    'created_by'      => $data->openedBy,
                    'updated_by'      => $data->openedBy,
                ]);

                // Insert into guard table — will throw PDO exception on duplicate PK
                CashierActiveSession::create([
                    'cashier_id'        => $data->cashierId,
                    'drawer_session_id' => $session->id,
                    'pos_device_id'     => $data->posDeviceId,
                    'shift_id'          => $data->shiftId,
                ]);

                // Record the opening balance as a cash movement
                $session->addMovement(
                    type:        CashMovementType::Opening,
                    amount:      $data->openingBalance,
                    performedBy: $data->openedBy,
                );

                return $session;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate PK on cashier_active_sessions — race condition
            if ($e->getCode() === '23000') {
                $cashierName = User::findOrFail($data->cashierId)->name;
                throw DrawerException::alreadyOpen($cashierName);
            }
            throw $e;
        }

        Log::info('Drawer session opened', [
            'session_id'      => $session->id,
            'session_number'  => $session->session_number,
            'cashier_id'      => $session->cashier_id,
            'shift_id'        => $session->shift_id,
            'opening_balance' => $session->opening_balance,
            'opened_by'       => $data->openedBy,
        ]);

        return $session->load(['cashier:id,name', 'posDevice:id,name', 'shift:id,shift_number']);
    }

    // ─────────────────────────────────────────
    // Close Drawer
    // ─────────────────────────────────────────

    /**
     * Close a drawer session and record the final reconciliation.
     *
     * Steps:
     *  1. Guard: session must be open
     *  2. Calculate expected balance from all movements
     *  3. Calculate variance (actual - expected)
     *  4. Update session to closed
     *  5. Delete the cashier_active_sessions guard row
     *
     * @throws DrawerException
     */
    public function close(
        CashierDrawerSession $session,
        User                 $actor,
        CloseDrawerData      $data,
    ): CashierDrawerSession
    {
        $this->authoriseSessionAccess($actor, $session, 'drawers.close');

        if ($session->isClosed()) {
            throw DrawerException::sessionClosed();
        }

        $this->consumeClosePreviewToken(
            session: $session,
            actor: $actor,
            actualCash: $data->actualCash,
            token: $data->previewToken,
        );

        $expected = $session->calculateExpectedBalance();
        $variance = round($data->actualCash - $expected, 2);

        if (abs($variance) >= 0.01 && blank($data->notes)) {
            throw DrawerException::varianceReasonRequired();
        }

        DB::transaction(function () use ($session, $data) {
            $expected = $session->calculateExpectedBalance();

            $session->update([
                'status'           => DrawerSessionStatus::Closed,
                'closing_balance'  => $data->actualCash,
                'expected_balance' => $expected,
                'cash_difference'  => $data->actualCash - $expected,
                'closed_by'        => $data->closedBy,
                'ended_at'         => now(),
                'notes'            => $data->notes,
                'updated_by'       => $data->closedBy,
            ]);

            // Release the guard record
            CashierActiveSession::where('cashier_id', $session->cashier_id)->delete();
        });

        Log::info('Drawer session closed', [
            'session_id'       => $session->id,
            'session_number'   => $session->session_number,
            'expected_balance' => $session->expected_balance,
            'actual_cash'      => $data->actualCash,
            'variance'         => $session->cash_difference,
            'closed_by'        => $data->closedBy,
        ]);

        $this->clearCloseReconciliationState($session, $actor, $data->previewToken);

        return $session->fresh(['cashier:id,name', 'posDevice:id,name']);
    }

    // ─────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────

    /**
     * Returns the cashier's currently open drawer session, or null.
     */
    public function getActiveSessionForCashier(int $cashierId): ?CashierDrawerSession
    {
        $guard = CashierActiveSession::with([
            'drawerSession.shift',
            'drawerSession.posDevice',
        ])->find($cashierId);
        $session = $guard?->drawerSession;

        if (!$session || !$session->isOpen()) {
            if ($guard) {
                Log::warning('Stale cashier active session guard detected', [
                    'cashier_id' => $cashierId,
                    'drawer_session_id' => $guard->drawer_session_id,
                    'session_exists' => $session !== null,
                    'session_status' => $session?->status?->value,
                ]);

                CashierActiveSession::where('cashier_id', $cashierId)->delete();
            }

            return null;
        }

        return $session;
    }

    /**
     * Returns the cashier's open drawer session or throws.
     *
     * @throws DrawerException
     */
    public function getOrFailActiveSession(int $cashierId): CashierDrawerSession
    {
        return $this->getActiveSessionForCashier($cashierId)
            ?? throw DrawerException::noActiveSession();
    }

    /**
     * Checks whether a cashier has an active session without throwing.
     */
    public function cashierHasActiveSession(int $cashierId): bool
    {
        return CashierActiveSession::where('cashier_id', $cashierId)->exists();
    }

    // ─────────────────────────────────────────
    // Manual Cash Movements
    // ─────────────────────────────────────────

    /**
     * Add cash into a drawer (e.g., manager topping up float).
     *
     * @throws DrawerException
     */
    public function cashIn(
        CashierDrawerSession $session,
        User                 $actor,
        CashMovementData     $data,
    ): CashMovement {
        $this->authoriseSessionAccess($actor, $session, 'drawers.cash_in');
        $this->assertSessionNotUnderReconciliation($session, $actor);

        if ($session->isClosed()) {
            throw DrawerException::sessionClosed();
        }

        if ($data->amount <= 0) {
            throw DrawerException::negativeAmount();
        }

        return DB::transaction(function () use ($session, $data): CashMovement {
            return $session->addMovement(
                type:        CashMovementType::CashIn,
                amount:      $data->amount,
                performedBy: $data->performedBy,
                notes:       $data->notes,
            );
        });
    }

    /**
     * Remove cash from a drawer (e.g., safe drop, petty cash).
     *
     * @throws DrawerException
     */
    public function cashOut(
        CashierDrawerSession $session,
        User                 $actor,
        CashMovementData     $data,
    ): CashMovement {
        $this->authoriseSessionAccess($actor, $session, 'drawers.cash_out');
        $this->assertSessionNotUnderReconciliation($session, $actor);

        if ($session->isClosed()) {
            throw DrawerException::sessionClosed();
        }

        if ($data->amount <= 0) {
            throw DrawerException::negativeAmount();
        }

        // Check available balance before allowing withdrawal
        $available = $session->calculateExpectedBalance();
        if ($data->amount > $available) {
            throw DrawerException::insufficientBalance($available, $data->amount);
        }

        return DB::transaction(function () use ($session, $data): CashMovement {
            return $session->addMovement(
                type:        CashMovementType::CashOut,
                amount:      $data->amount,
                performedBy: $data->performedBy,
                notes:       $data->notes,
            );
        });
    }

    // ─────────────────────────────────────────
    // Reporting
    // ─────────────────────────────────────────

    /**
     * Returns a full balance breakdown for a session.
     * Used in the drawer summary screen before closing.
     */
    public function getSessionSummary(CashierDrawerSession $session, User $actor): array
    {
        $this->authoriseSessionAccess(
            $actor,
            $session,
            'drawer_sessions.view',
            'drawer_sessions.viewAny',
        );

        if ($this->shouldHideLiveFinancialSummary($actor, $session)) {
            throw DrawerException::unauthorized('عرض الإحصائيات المالية قبل جرد الدرج');
        }

        return $this->buildSessionSummary($session);
    }

    public function getClosePreview(
        CashierDrawerSession $session,
        User $actor,
        float $actualCash,
    ): array {
        $this->authoriseSessionAccess($actor, $session, 'drawers.close');

        if ($session->isClosed()) {
            throw DrawerException::sessionClosed();
        }

        if ($existingLock = $this->getCloseReconciliationState($session, $actor)) {
            return $existingLock;
        }

        $summary = $this->buildSessionSummary($session);
        $variance = round($actualCash - (float) $summary['expected_cash'], 2);
        $token = (string) Str::uuid();

        Cache::put(
            self::CLOSE_PREVIEW_CACHE_PREFIX . $token,
            [
                'session_id' => $session->id,
                'actor_id' => $actor->id,
                'actual_cash' => round($actualCash, 2),
            ],
            now()->addMinutes(self::CLOSE_PREVIEW_TTL_MINUTES),
        );

        $preview = array_merge($summary, [
            'actual_cash' => round($actualCash, 2),
            'variance' => $variance,
            'matches_expected' => abs($variance) < 0.01,
            'can_close' => true,
            'close_block_reason' => null,
            'requires_variance_reason' => abs($variance) >= 0.01,
            'preview_token' => $token,
            'locked' => true,
        ]);

        Cache::put(
            $this->closeReconciliationLockKey($session->id, $actor->id),
            $preview,
            now()->addMinutes(self::CLOSE_PREVIEW_TTL_MINUTES),
        );

        return $preview;
    }

    public function getCloseReconciliationState(CashierDrawerSession $session, User $actor): ?array
    {
        return $this->getCloseReconciliationStateForActor($session, $actor->id);
    }

    public function getCloseReconciliationStateForActor(CashierDrawerSession $session, int $actorId): ?array
    {
        $payload = Cache::get($this->closeReconciliationLockKey($session->id, $actorId));

        return is_array($payload) ? $payload : null;
    }

    public function assertSessionNotUnderReconciliation(CashierDrawerSession $session, User $actor): void
    {
        $this->assertSessionNotUnderReconciliationForActor($session, $actor->id);
    }

    public function assertSessionNotUnderReconciliationForActor(CashierDrawerSession $session, int $actorId): void
    {
        if ($this->getCloseReconciliationStateForActor($session, $actorId)) {
            throw DrawerException::reconciliationLocked();
        }
    }

    private function authoriseOpen(User $actor, OpenDrawerData $data): void
    {
        if ($actor->id === $data->cashierId) {
            if ($actor->canAccessPosSurface()) {
                return;
            }

            throw DrawerException::unauthorized('استخدام نقطة البيع');
        }

        if (!$actor->canAccessPosSurface()) {
            throw DrawerException::unauthorized('استخدام نقطة البيع');
        }

        if ($actor->hasPermission('drawers.open')) {
            return;
        }

        throw DrawerException::unauthorized('فتح درج لكاشير آخر');
    }

    private function authoriseSessionAccess(
        User                 $actor,
        CashierDrawerSession $session,
        string               ...$permissions,
    ): void {
        if ($actor->id === $session->cashier_id) {
            return;
        }

        if ($actor->hasAnyPermission($permissions)) {
            return;
        }

        throw DrawerException::unauthorized('إدارة جلسة درج لا تخصك');
    }

    private function shouldHideLiveFinancialSummary(User $actor, CashierDrawerSession $session): bool
    {
        return $session->isOpen()
            && $actor->id === $session->cashier_id
            && $actor->mustDeclareCashBeforeSeeingSessionFinancialStats();
    }

    private function buildSessionSummary(CashierDrawerSession $session): array
    {
        $movements = $session->cashMovements()->orderBy('created_at')->get();
        $orders = $session->reportableOrders()->with(['payments', 'settlement'])->get();

        $byType = fn (string $type) => $movements
            ->filter(fn ($m) => $m->type->value === $type)
            ->sum(fn ($m) => (float) $m->amount);

        $cashSales = round($orders->sum(fn ($order) => $order->reportableCashPaidAmount()), 2);
        $nonCashSales = round($orders->sum(fn ($order) => $order->reportableNonCashPaidAmount()), 2);
        $cashIn = (float) $byType('cash_in');
        $cashOut = (float) $byType('cash_out');
        $refunds = (float) $byType('refund');
        $expectedCash = $session->calculateExpectedBalance();
        $grossIn = round((float) $session->opening_balance + $cashSales + $cashIn, 2);
        $grossOut = round($refunds + $cashOut, 2);

        return [
            'session_number'       => $session->session_number,
            'cashier'              => $session->cashier->name,
            'pos_device'           => $session->posDevice->name,
            'status'               => $session->status->label(),
            'started_at'           => $session->started_at,
            'ended_at'             => $session->ended_at,
            'opening_balance'      => (float) $session->opening_balance,
            'cash_sales'           => $cashSales,
            'non_cash_sales'       => $nonCashSales,
            'total_refunds'        => $refunds,
            'cash_in'              => $cashIn,
            'cash_out'             => $cashOut,
            'gross_in'             => $grossIn,
            'gross_out'            => $grossOut,
            'expected_cash'        => $expectedCash,
            'expected_balance'     => $expectedCash,
            'closing_balance'      => $session->closing_balance ? (float) $session->closing_balance : null,
            'variance'             => $session->cash_difference ? (float) $session->cash_difference : null,
            'order_count'          => $orders->count(),
            'paid_orders_count'    => $orders->where('payment_status.value', 'paid')->count(),
            'pending_orders_count' => $orders->where('payment_status.value', 'pending')->count(),
            'movements'            => $movements->map(fn ($m) => [
                'type'      => $m->type->label(),
                'direction' => $m->direction->label(),
                'amount'    => (float) $m->amount,
                'notes'     => $m->notes,
                'at'        => $m->created_at,
            ])->values(),
        ];
    }

    private function consumeClosePreviewToken(
        CashierDrawerSession $session,
        User $actor,
        float $actualCash,
        ?string $token,
    ): void {
        if (!$token) {
            throw DrawerException::closeDeclarationRequired();
        }

        $payload = Cache::get(self::CLOSE_PREVIEW_CACHE_PREFIX . $token);

        if (
            !$payload ||
            (int) ($payload['session_id'] ?? 0) !== $session->id ||
            (int) ($payload['actor_id'] ?? 0) !== $actor->id ||
            round((float) ($payload['actual_cash'] ?? 0), 2) !== round($actualCash, 2)
        ) {
            throw DrawerException::closeDeclarationRequired();
        }
    }

    private function clearCloseReconciliationState(CashierDrawerSession $session, User $actor, ?string $token): void
    {
        Cache::forget($this->closeReconciliationLockKey($session->id, $actor->id));

        if ($token) {
            Cache::forget(self::CLOSE_PREVIEW_CACHE_PREFIX . $token);
        }
    }

    private function closeReconciliationLockKey(int $sessionId, int $actorId): string
    {
        return self::CLOSE_RECONCILIATION_LOCK_PREFIX . $sessionId . ':' . $actorId;
    }
}
