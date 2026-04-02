<?php

namespace App\Services;

use App\DTOs\CashMovementData;
use App\Enums\CashMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\DrawerException;
use App\Models\CashierDrawerSession;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\Shift;
use App\Support\BusinessTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashManagementService
{
    // ─────────────────────────────────────────
    // Manual Cash Movements
    // ─────────────────────────────────────────

    /**
     * Add cash into a drawer (float top-up, manager injection, etc.)
     *
     * @throws DrawerException
     */
    public function cashIn(CashierDrawerSession $session, CashMovementData $data): CashMovement
    {
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
     * Remove cash from a drawer (safe drop, petty cash withdrawal, etc.)
     *
     * Validates that the drawer has sufficient balance before allowing removal.
     *
     * @throws DrawerException
     */
    public function cashOut(CashierDrawerSession $session, CashMovementData $data): CashMovement
    {
        if ($session->isClosed()) {
            throw DrawerException::sessionClosed();
        }

        if ($data->amount <= 0) {
            throw DrawerException::negativeAmount();
        }

        // Prevent overdraft
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
    // Drawer Summary (pre-close view)
    // ─────────────────────────────────────────

    /**
     * Full balance breakdown for a single drawer session.
     * Used on the cashier's "Close Drawer" screen before reconciliation.
     */
    public function getDrawerSummary(CashierDrawerSession $session): array
    {
        $movements = $session->cashMovements()
                             ->with('performer:id,name')
                             ->orderBy('created_at')
                             ->get();

        $sum = fn (string $type) => $movements
            ->filter(fn ($m) => $m->type->value === $type)
            ->sum(fn ($m) => (float) $m->amount);

        $cashSales = $session->reportableCashSalesTotal();
        $cashIn = round((float) $sum('cash_in'), 2);
        $cashOut = round((float) $sum('cash_out'), 2);
        $refunds = round((float) $sum('refund'), 2);
        $expected = $session->calculateExpectedBalance();

        return [
            'session_number'   => $session->session_number,
            'cashier'          => $session->cashier->name,
            'pos_device'       => $session->posDevice->name,
            'status'           => $session->status->label(),
            'started_at'       => BusinessTime::formatDateTime($session->started_at),
            'ended_at'         => BusinessTime::formatDateTime($session->ended_at),
            // Money breakdown
            'opening_balance'  => round((float) $session->opening_balance, 2),
            'total_sales'      => $cashSales,
            'total_refunds'    => $refunds,
            'total_cash_in'    => $cashIn,
            'total_cash_out'   => $cashOut,
            'expected_balance' => $expected,
            // Post-close (nulls until closed)
            'closing_balance'  => $session->closing_balance ? round((float) $session->closing_balance, 2) : null,
            'variance'         => $session->cash_difference ? round((float) $session->cash_difference, 2) : null,
            // Orders
            'order_count'      => $session->orders()
                                          ->whereNotIn('status', [OrderStatus::Cancelled->value])
                                          ->count(),
            // Movement log
            'movements'        => $movements->map(fn ($m) => [
                'id'           => $m->id,
                'type'         => $m->type->label(),
                'direction'    => $m->direction->label(),
                'amount'       => round((float) $m->amount, 2),
                'notes'        => $m->notes,
                'performed_by' => $m->performer?->name,
                'at'           => blank($m->created_at) ? null : BusinessTime::asLocal($m->created_at)->format('H:i:s'),
            ])->values()->all(),
        ];
    }

    // ─────────────────────────────────────────
    // Shift Summary (manager close-shift view)
    // ─────────────────────────────────────────

    /**
     * Aggregated financial summary across all drawers within a shift.
     * Used on the manager's "Close Shift" confirmation screen.
     */
    public function getShiftSummary(Shift $shift): array
    {
        $sessions = $shift->drawerSessions()
                          ->with(['cashier:id,name', 'posDevice:id,name', 'cashMovements'])
                          ->get();

        $orders = $shift->orders()
                        ->with(['payments', 'settlement'])
                        ->revenueReportable()
                        ->get();

        // Revenue by payment method
        $paymentBreakdown = collect(PaymentMethod::cases())
            ->mapWithKeys(fn (PaymentMethod $method) => [
                $method->value => round(
                    $orders->sum(fn ($order) => $order->reportablePaidAmountForMethod($method)),
                    2,
                ),
            ])
            ->filter(fn (float $amount) => $amount > 0);

        // Order status breakdown
        $statusBreakdown = $orders->groupBy(fn ($o) => $o->status->value)
                                  ->map->count();

        // Per-drawer summaries
        $drawerSummaries = $sessions->map(fn ($s) => $this->getDrawerSummary($s))->values();

        // Aggregate cash position (drawer-based)
        $totalExpected = $drawerSummaries->sum('expected_balance');
        $totalActual   = $sessions->filter->isClosed()->sum(fn ($s) => (float) $s->closing_balance);
        $totalVariance = $sessions->filter->isClosed()->sum(fn ($s) => (float) $s->cash_difference);

        // Expenses paid from drawers
        $totalExpenses = $shift->expenses()->sum('amount');

        return [
            'shift_number'          => $shift->shift_number,
            'status'                => $shift->status->label(),
            'started_at'            => BusinessTime::formatDateTime($shift->started_at),
            'ended_at'              => BusinessTime::formatDateTime($shift->ended_at),
            'duration'              => $shift->durationLabel(),
            'opened_by'             => $shift->opener->name,
            'closed_by'             => $shift->closer?->name,
            // Orders
            'total_orders'          => $orders->count(),
            'status_breakdown'      => $statusBreakdown,
            // Revenue
            'gross_revenue'         => round($orders->sum(fn ($o) => (float) $o->total), 2),
            'total_discounts'       => round($orders->sum(fn ($o) => (float) $o->discount_amount), 2),
            'total_tax'             => round($orders->sum(fn ($o) => (float) $o->tax_amount), 2),
            'total_delivery_fees'   => round($orders->sum(fn ($o) => (float) $o->delivery_fee), 2),
            'total_refunds'         => round($orders->sum(fn ($o) => (float) $o->refund_amount), 2),
            // Payment breakdown
            'payment_breakdown'     => $paymentBreakdown,
            // Cash position (drawer-based)
            'total_expected_cash'   => round($totalExpected, 2),
            'total_actual_cash'     => round($totalActual, 2),
            'total_variance'        => round($totalVariance, 2),
            // Expenses
            'total_expenses'        => round((float) $totalExpenses, 2),
            'net_cash'              => round($totalActual - (float) $totalExpenses, 2),
            // Drawers
            'drawer_count'          => $sessions->count(),
            'drawers'               => $drawerSummaries,
        ];
    }

    // ─────────────────────────────────────────
    // Expense Cash Movement
    // ─────────────────────────────────────────

    /**
     * Record a cash expense paid out of a drawer session.
     * Creates both an Expense record and a CashOut movement, linked together.
     *
     * @throws DrawerException
     */
    public function recordCashExpense(
        CashierDrawerSession $session,
        int                  $categoryId,
        float                $amount,
        string               $description,
        string               $expenseDate,
        int                  $performedBy,
        ?string              $receiptNumber = null,
        ?string              $notes         = null,
    ): Expense {
        if ($session->isClosed()) {
            throw DrawerException::sessionClosed();
        }

        if ($amount <= 0) {
            throw DrawerException::negativeAmount();
        }

        $available = $session->calculateExpectedBalance();
        if ($amount > $available) {
            throw DrawerException::insufficientBalance($available, $amount);
        }

        return DB::transaction(function () use (
            $session, $categoryId, $amount, $description,
            $expenseDate, $performedBy, $receiptNumber, $notes
        ): Expense {
            // Create the expense record
            $expense = Expense::create([
                'expense_number'    => null,           // auto-generated in booted()
                'category_id'       => $categoryId,
                'shift_id'          => $session->shift_id,
                'drawer_session_id' => $session->id,
                'amount'            => $amount,
                'description'       => $description,
                'payment_method'    => 'cash',
                'receipt_number'    => $receiptNumber,
                'expense_date'      => $expenseDate,
                'notes'             => $notes,
                'created_by'        => $performedBy,
                'updated_by'        => $performedBy,
            ]);

            // Deduct from drawer balance
            $session->addMovement(
                type:          CashMovementType::CashOut,
                amount:        $amount,
                performedBy:   $performedBy,
                referenceType: 'expense',
                referenceId:   $expense->id,
                notes:         "مصروف: {$description}",
            );

            return $expense;
        });
    }

    // ─────────────────────────────────────────
    // Variance Analysis
    // ─────────────────────────────────────────

    /**
     * Returns sessions with variances above the acceptable threshold.
     * Used in the daily cash report to flag discrepancies.
     *
     * @param  float  $threshold  Absolute variance amount to flag (e.g. 5.00)
     * @return Collection<CashierDrawerSession>
     */
    public function getVariantSessions(Shift $shift, float $threshold = 5.00): Collection
    {
        return $shift->drawerSessions()
                     ->with('cashier:id,name')
                     ->where('status', 'closed')
                     ->whereRaw('ABS(cash_difference) > ?', [$threshold])
                     ->orderByRaw('ABS(cash_difference) DESC')
                     ->get()
                     ->map(fn ($s) => [
                         'cashier'       => $s->cashier->name,
                         'session'       => $s->session_number,
                         'expected'      => round((float) $s->expected_balance, 2),
                         'actual'        => round((float) $s->closing_balance, 2),
                         'variance'      => round((float) $s->cash_difference, 2),
                         'variance_pct'  => $s->expected_balance > 0
                             ? round(abs((float) $s->cash_difference / (float) $s->expected_balance) * 100, 1)
                             : 0,
                     ]);
    }
}
