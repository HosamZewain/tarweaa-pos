<?php

namespace App\Services;

use App\DTOs\CloseShiftData;
use App\DTOs\OpenShiftData;
use App\Enums\ShiftStatus;
use App\Exceptions\ShiftException;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftService
{
    // ─────────────────────────────────────────
    // Open Shift
    // ─────────────────────────────────────────

    /**
     * Open a new shift.
     *
     * Rules:
     *  - Only one open shift can exist at a time
     *  - The opener must have the 'shifts.open' permission
     *
     * @throws ShiftException
     */
    public function open(User $opener, OpenShiftData $data): Shift
    {
        $this->authorise($opener, 'shifts.open');

        // Hard check: is there already an open shift?
        if (Shift::open()->exists()) {
            throw ShiftException::alreadyOpen();
        }

        return DB::transaction(function () use ($opener, $data): Shift {
            $shift = Shift::create([
                'shift_number' => Shift::generateShiftNumber(),
                'status'       => ShiftStatus::Open,
                'opened_by'    => $opener->id,
                'started_at'   => now(),
                'notes'        => $data->notes,
                'created_by'   => $opener->id,
                'updated_by'   => $opener->id,
            ]);

            Log::info('Shift opened', [
                'shift_id'     => $shift->id,
                'shift_number' => $shift->shift_number,
                'opened_by'    => $opener->id,
            ]);

            return $shift;
        });
    }

    // ─────────────────────────────────────────
    // Close Shift
    // ─────────────────────────────────────────

    /**
     * Close an open shift.
     *
     * Rules:
     *  - Shift must currently be open
     *  - ALL cashier drawer sessions within the shift must be closed first
     *  - Calculates and stores expected_cash, actual_cash, cash_difference
     *
     * @throws ShiftException
     */
    public function close(Shift $shift, User $closer, CloseShiftData $data): Shift
    {
        $this->authorise($closer, 'shifts.close');

        if ($shift->isClosed()) {
            throw ShiftException::alreadyClosed();
        }

        // Block close if any drawer sessions are still open
        $openDrawers = $shift->openDrawerSessions()
                             ->with('cashier:id,name')
                             ->get();

        if ($openDrawers->isNotEmpty()) {
            throw ShiftException::cannotCloseWithOpenDrawers(
                $openDrawers->pluck('cashier.name')
            );
        }

        return DB::transaction(function () use ($shift, $closer, $data): Shift {
            // ── Drawer-based cash reconciliation ───────────────────────
            // Sum expected balance from each drawer session (the single source of truth)
            $shift->load('drawerSessions');
            $expectedCash = $shift->calculateExpectedCashFromDrawers();

            $shift->update([
                'status'          => ShiftStatus::Closed,
                'closed_by'       => $closer->id,
                'ended_at'        => now(),
                'notes'           => $data->notes ?? $shift->notes,
                'expected_cash'   => $expectedCash,
                'actual_cash'     => $data->actualCash,
                'cash_difference' => $data->actualCash - $expectedCash,
                'updated_by'      => $closer->id,
            ]);

            Log::info('Shift closed', [
                'shift_id'        => $shift->id,
                'shift_number'    => $shift->shift_number,
                'closed_by'       => $closer->id,
                'expected_cash'   => $shift->expected_cash,
                'actual_cash'     => $shift->actual_cash,
                'cash_difference' => $shift->cash_difference,
            ]);

            return $shift->fresh();
        });
    }

    // ─────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────

    /**
     * Returns the current open shift, or null if none.
     */
    public function getActiveShift(): ?Shift
    {
        return Shift::open()->latest('started_at')->first();
    }

    /**
     * Returns the current open shift, throws if none exists.
     *
     * @throws ShiftException
     */
    public function getOrFailActiveShift(): Shift
    {
        return $this->getActiveShift() ?? throw ShiftException::noActiveShift();
    }

    /**
     * Whether the shift has the prerequisites to be closed.
     */
    public function canClose(Shift $shift): bool
    {
        return $shift->isOpen()
            && $shift->openDrawerSessions()->doesntExist();
    }

    /**
     * Returns a detailed status summary used for shift-close confirmation UI.
     */
    public function getCloseSummary(Shift $shift): array
    {
        $openDrawers = $shift->openDrawerSessions()
                             ->with('cashier:id,name', 'posDevice:id,name')
                             ->get();

        return [
            'shift_number'       => $shift->shift_number,
            'started_at'         => $shift->started_at,
            'duration_label'     => $shift->durationLabel(),
            'total_orders'       => $shift->totalOrders(),
            'total_revenue'      => $shift->totalRevenue(),
            'can_close'          => $openDrawers->isEmpty(),
            'open_drawers_count' => $openDrawers->count(),
            'open_drawers'       => $openDrawers->map(fn ($d) => [
                'cashier'    => $d->cashier->name,
                'pos_device' => $d->posDevice->name,
                'started_at' => $d->started_at,
            ])->values(),
        ];
    }

    // ─────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────

    private function authorise(User $user, string $permission): void
    {
        if (!$user->hasPermission($permission)) {
            throw ShiftException::unauthorized($permission);
        }
    }
}
