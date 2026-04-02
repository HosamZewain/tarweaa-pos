<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CashMovementData;
use App\DTOs\CloseDrawerData;
use App\DTOs\OpenDrawerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drawer\CashMovementRequest;
use App\Http\Requests\Drawer\CloseDrawerRequest;
use App\Http\Requests\Drawer\OpenDrawerRequest;
use App\Models\CashierDrawerSession;
use App\Services\AdminActivityLogService;
use App\Services\DrawerSessionService;
use App\Services\ManagerVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrawerController extends Controller
{
    public function __construct(
        private readonly DrawerSessionService $drawerService,
        private readonly ManagerVerificationService $managerVerificationService,
        private readonly AdminActivityLogService $adminActivityLogService,
    ) {}

    /**
     * POST /api/drawers/open — Open a drawer session for a cashier.
     */
    public function open(OpenDrawerRequest $request): JsonResponse
    {
        $session = $this->drawerService->open(
            actor: $request->user(),
            data: OpenDrawerData::fromArray(array_merge(
                $request->validated(),
                ['opened_by' => $request->user()->id],
            )),
        );

        return $this->created($session, 'تم فتح الدرج بنجاح');
    }

    /**
     * POST /api/drawers/{session}/close-preview — Preview drawer close summary after physical count.
     */
    public function previewClose(CloseDrawerRequest $request, CashierDrawerSession $session): JsonResponse
    {
        $preview = $this->drawerService->getClosePreview(
            session: $session,
            actor: $request->user(),
            actualCash: (float) $request->validated('actual_cash'),
        );

        return $this->success($preview);
    }

    /**
     * POST /api/drawers/{session}/close — Close a drawer session.
     */
    public function close(CloseDrawerRequest $request, CashierDrawerSession $session): JsonResponse
    {
        $session = $this->drawerService->close(
            session: $session,
            actor: $request->user(),
            data: CloseDrawerData::fromArray(array_merge(
                $request->validated(),
                ['closed_by' => $request->user()->id],
            )),
        );

        return $this->success($session, 'تم إغلاق الدرج بنجاح');
    }

    /**
     * GET /api/drawers/active — Get the current cashier's active drawer session.
     */
    public function active(Request $request): JsonResponse
    {
        $session = $this->drawerService->getActiveSessionForCashier(
            cashierId: $request->user()->id,
        );

        if (!$session) {
            return $this->success(null, 'لا توجد جلسة درج مفتوحة');
        }

        return $this->success(array_merge(
            $session->toArray(),
            [
                'close_reconciliation' => $this->drawerService->getCloseReconciliationState(
                    session: $session,
                    actor: $request->user(),
                ),
            ],
        ));
    }

    /**
     * GET /api/drawers/{session}/summary — Drawer session balance summary.
     */
    public function summary(CashierDrawerSession $session): JsonResponse
    {
        $summary = $this->drawerService->getSessionSummary($session, request()->user());

        return $this->success($summary);
    }

    /**
     * POST /api/drawers/{session}/cash-in — Add cash to drawer.
     */
    public function cashIn(CashMovementRequest $request, CashierDrawerSession $session): JsonResponse
    {
        $approver = $this->resolveManagerApprover(
            approverId: (int) $request->validated('approver_id'),
            approverPin: $request->validated('approver_pin'),
        );

        $movement = $this->drawerService->cashIn(
            session: $session,
            actor: $request->user(),
            data: CashMovementData::fromArray(array_merge(
                $request->validated(),
                ['performed_by' => $request->user()->id],
            )),
        );

        $this->logCashMovementApproval(
            action: 'cash_in_recorded',
            movement: $movement,
            approver: $approver,
        );

        return $this->created($movement, 'تم إضافة المبلغ بنجاح');
    }

    /**
     * POST /api/drawers/{session}/cash-out — Remove cash from drawer.
     */
    public function cashOut(CashMovementRequest $request, CashierDrawerSession $session): JsonResponse
    {
        $approver = $this->resolveManagerApprover(
            approverId: (int) $request->validated('approver_id'),
            approverPin: $request->validated('approver_pin'),
        );

        $movement = $this->drawerService->cashOut(
            session: $session,
            actor: $request->user(),
            data: CashMovementData::fromArray(array_merge(
                $request->validated(),
                ['performed_by' => $request->user()->id],
            )),
        );

        $this->logCashMovementApproval(
            action: 'cash_out_recorded',
            movement: $movement,
            approver: $approver,
        );

        return $this->created($movement, 'تم سحب المبلغ بنجاح');
    }

    private function resolveManagerApprover(int $approverId, string $approverPin): \App\Models\User
    {
        $approver = $this->managerVerificationService->findApprover($approverId);

        if (!$approver) {
            throw \App\Exceptions\DrawerException::managerApproverInvalid();
        }

        if (!$this->managerVerificationService->verifyPin($approver, $approverPin)) {
            throw \App\Exceptions\DrawerException::managerApproverPinInvalid();
        }

        return $approver;
    }

    private function logCashMovementApproval(string $action, \App\Models\CashMovement $movement, \App\Models\User $approver): void
    {
        $movement->loadMissing(['drawerSession.cashier', 'drawerSession.posDevice', 'performer']);

        $this->adminActivityLogService->logAction(
            action: $action,
            subject: $movement,
            description: $action === 'cash_in_recorded'
                ? 'تم تسجيل إيداع نقدي يدوي بعد اعتماد المدير.'
                : 'تم تسجيل سحب نقدي يدوي بعد اعتماد المدير.',
            newValues: [
                'movement_type' => $movement->type?->value,
                'direction' => $movement->direction?->value,
                'amount' => (float) $movement->amount,
                'notes' => $movement->notes,
                'session_number' => $movement->drawerSession?->session_number,
                'cashier_name' => $movement->drawerSession?->cashier?->name,
                'pos_device_name' => $movement->drawerSession?->posDevice?->name,
            ],
            meta: [
                'approved_by_user_id' => $approver->id,
                'approved_by_name' => $approver->name,
                'approved_by_username' => $approver->username,
                'performed_by_user_id' => $movement->performed_by,
                'performed_by_name' => $movement->performer?->name,
                'drawer_session_id' => $movement->drawer_session_id,
                'cash_movement_id' => $movement->id,
            ],
            module: 'drawer_sessions',
            subjectLabel: ($action === 'cash_in_recorded' ? 'إيداع نقدي' : 'سحب نقدي') . ' - ' . ($movement->drawerSession?->session_number ?? $movement->id),
            force: true,
        );
    }
}
