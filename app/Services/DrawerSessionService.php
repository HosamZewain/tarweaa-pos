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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DrawerSessionService
{
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

        return $guard?->drawerSession;
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

        $movements = $session->cashMovements()->orderBy('created_at')->get();
        $orders    = $session->orders()->get();

        $byType = fn (string $type) => $movements
            ->filter(fn ($m) => $m->type->value === $type)
            ->sum(fn ($m) => (float) $m->amount);

        $totalIn  = (float) $movements->where('direction.value', 'in')->sum('amount');
        $totalOut = (float) $movements->where('direction.value', 'out')->sum('amount');

        // Non-cash sales: payments for these orders that are NOT cash
        $nonCashSales = \App\Models\OrderPayment::whereIn('order_id', $orders->pluck('id'))
            ->where('payment_method', '!=', \App\Enums\PaymentMethod::Cash)
            ->sum('amount');

        return [
            'session_number'      => $session->session_number,
            'cashier'             => $session->cashier->name,
            'pos_device'          => $session->posDevice->name,
            'status'              => $session->status->label(),
            'started_at'          => $session->started_at,
            'ended_at'            => $session->ended_at,
            'opening_balance'     => (float) $session->opening_balance,
            'cash_sales'          => $byType('sale'),
            'non_cash_sales'      => (float) $nonCashSales,
            'total_refunds'       => $byType('refund'),
            'cash_in'             => $byType('cash_in'),
            'cash_out'            => $byType('cash_out'),
            'gross_in'            => $totalIn,
            'gross_out'           => $totalOut,
            'expected_cash'       => $session->calculateExpectedBalance(),
            'expected_balance'    => $session->calculateExpectedBalance(),
            'closing_balance'     => $session->closing_balance ? (float) $session->closing_balance : null,
            'variance'            => $session->cash_difference ? (float) $session->cash_difference : null,
            'order_count'         => $orders->count(),
            'paid_orders_count'   => $orders->where('payment_status.value', 'paid')->count(),
            'pending_orders_count'=> $orders->where('payment_status.value', 'pending')->count(),
            'movements'           => $movements->map(fn ($m) => [
                'type'      => $m->type->label(),
                'direction' => $m->direction->label(),
                'amount'    => (float) $m->amount,
                'notes'     => $m->notes,
                'at'        => $m->created_at,
            ])->values(),
        ];
    }

    private function authoriseOpen(User $actor, OpenDrawerData $data): void
    {
        if ($actor->id === $data->cashierId) {
            return;
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
}
