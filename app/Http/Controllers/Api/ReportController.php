<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\RecordExpenseRequest;
use App\Models\CashierDrawerSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use App\Services\CashManagementService;
use App\Services\DrawerSessionService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly CashManagementService $cashService,
        private readonly DrawerSessionService $drawerService,
        private readonly ReportService $reportService,
    ) {}

    /**
     * GET /api/reports/shifts/{shift} — Full shift financial summary.
     */
    public function shiftSummary(Shift $shift): JsonResponse
    {
        $summary = $this->cashService->getShiftSummary($shift);

        return $this->success($summary);
    }

    /**
     * GET /api/reports/drawers/{session} — Drawer session balance summary.
     */
    public function drawerSummary(CashierDrawerSession $session): JsonResponse
    {
        $summary = $this->cashService->getDrawerSummary($session);

        return $this->success($summary);
    }

    /**
     * GET /api/reports/daily-sales — Revenue summary by date range.
     */
    public function dailySales(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
        ], [
            'date_from.required'      => 'تاريخ البداية مطلوب.',
            'date_to.required'        => 'تاريخ النهاية مطلوب.',
            'date_to.after_or_equal'  => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.',
        ]);

        $data = $this->reportService->getDailySales($request->date_from, $request->date_to);

        return $this->success($data);
    }

    /**
     * GET /api/reports/cash-variance/{shift} — Flag sessions with cash discrepancies.
     */
    public function cashVariance(Request $request, Shift $shift): JsonResponse
    {
        $threshold = (float) $request->get('threshold', 5.00);

        $flagged = $this->cashService->getVariantSessions($shift, $threshold);

        return $this->success($flagged);
    }

    /**
     * GET /api/reports/expenses — Expense report with filters.
     */
    public function expenses(Request $request): JsonResponse
    {
        $expenses = Expense::with(['category:id,name', 'creator:id,name', 'approver:id,name'])
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->shift_id, fn ($q, $id) => $q->where('shift_id', $id))
            ->when($request->date_from, fn ($q, $date) => $q->where('expense_date', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->where('expense_date', '<=', $date))
            ->when($request->payment_method, fn ($q, $method) => $q->where('payment_method', $method))
            ->orderByDesc('expense_date')
            ->paginate($request->get('per_page', 25));

        return $this->paginated($expenses);
    }

    /**
     * POST /api/reports/expenses — Record a cash expense from a drawer.
     */
    public function recordExpense(RecordExpenseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        // Get the cashier's active drawer session
        $session = $this->drawerService->getOrFailActiveSession($user->id);

        $expense = $this->cashService->recordCashExpense(
            session:       $session,
            categoryId:    (int) $validated['category_id'],
            amount:        (float) $validated['amount'],
            description:   $validated['description'],
            expenseDate:   $validated['expense_date'],
            performedBy:   $user->id,
            receiptNumber: $validated['receipt_number'] ?? null,
            notes:         $validated['notes'] ?? null,
        );

        return $this->created($expense, 'تم تسجيل المصروف بنجاح');
    }

    /**
     * GET /api/reports/expense-categories — List expense categories.
     */
    public function expenseCategories(): JsonResponse
    {
        $categories = ExpenseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return $this->success($categories);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEW REPORTING ENDPOINTS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/reports/sales-by-item — Top selling items
     */
    public function salesByItem(Request $request): JsonResponse
    {
        $items = $this->reportService->getSalesByItem(
            $request->date_from,
            $request->date_to,
            $request->get('limit', 50)
        );

        return $this->success($items);
    }

    /**
     * GET /api/reports/sales-by-category — Revenue grouped by menu category
     */
    public function salesByCategory(Request $request): JsonResponse
    {
        $categories = $this->reportService->getSalesByCategory(
            $request->date_from,
            $request->date_to
        );

        return $this->success($categories);
    }

    /**
     * GET /api/reports/sales-by-payment-method — Revenue by payment type
     */
    public function salesByPaymentMethod(Request $request): JsonResponse
    {
        $payments = $this->reportService->getSalesByPaymentMethod(
            $request->date_from,
            $request->date_to
        );

        return $this->success($payments);
    }

    /**
     * GET /api/reports/drawers-reconciliation — Paginated list of closed drawers for auditing
     */
    public function drawersReconciliation(Request $request): JsonResponse
    {
        $sessions = $this->reportService->getDrawersReconciliation(
            $request->date_from,
            $request->date_to,
            $request->get('per_page', 25)
        );

        return $this->paginated($sessions);
    }

    /**
     * GET /api/reports/inventory-valuation — Total stock value logically grouped
     */
    public function inventoryValuation(): JsonResponse
    {
        $valuation = $this->reportService->getInventoryValuation();

        return $this->success($valuation);
    }

    /**
     * GET /api/reports/inventory-movements — Summary of stock transactions
     */
    public function inventoryMovements(Request $request): JsonResponse
    {
        $movements = $this->reportService->getInventoryMovements(
            $request->date_from,
            $request->date_to
        );

        return $this->success($movements);
    }
}
