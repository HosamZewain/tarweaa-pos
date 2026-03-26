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
use App\Services\DrawerSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrawerController extends Controller
{
    public function __construct(
        private readonly DrawerSessionService $drawerService,
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

        return $this->success($session);
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
        $movement = $this->drawerService->cashIn(
            session: $session,
            actor: $request->user(),
            data: CashMovementData::fromArray(array_merge(
                $request->validated(),
                ['performed_by' => $request->user()->id],
            )),
        );

        return $this->created($movement, 'تم إضافة المبلغ بنجاح');
    }

    /**
     * POST /api/drawers/{session}/cash-out — Remove cash from drawer.
     */
    public function cashOut(CashMovementRequest $request, CashierDrawerSession $session): JsonResponse
    {
        $movement = $this->drawerService->cashOut(
            session: $session,
            actor: $request->user(),
            data: CashMovementData::fromArray(array_merge(
                $request->validated(),
                ['performed_by' => $request->user()->id],
            )),
        );

        return $this->created($movement, 'تم سحب المبلغ بنجاح');
    }
}
