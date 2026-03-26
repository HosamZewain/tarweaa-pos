<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CloseShiftData;
use App\DTOs\OpenShiftData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\CloseShiftRequest;
use App\Http\Requests\Shift\OpenShiftRequest;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
    ) {}

    /**
     * GET /api/shifts — Paginated shift history.
     */
    public function index(Request $request): JsonResponse
    {
        $shifts = Shift::with(['opener:id,name', 'closer:id,name'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->date_from, fn ($q, $date) => $q->where('started_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->where('started_at', '<=', $date))
            ->orderByDesc('started_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($shifts);
    }

    /**
     * GET /api/shifts/{shift} — Single shift details.
     */
    public function show(Shift $shift): JsonResponse
    {
        $shift->load([
            'opener:id,name',
            'closer:id,name',
            'drawerSessions.cashier:id,name',
            'drawerSessions.posDevice:id,name',
        ]);

        return $this->success($shift);
    }

    /**
     * POST /api/shifts/open — Open a new shift.
     */
    public function open(OpenShiftRequest $request): JsonResponse
    {
        $shift = $this->shiftService->open(
            opener: $request->user(),
            data: OpenShiftData::fromArray($request->validated()),
        );

        return $this->created($shift, 'تم فتح الوردية بنجاح');
    }

    /**
     * POST /api/shifts/{shift}/close — Close an open shift.
     */
    public function close(CloseShiftRequest $request, Shift $shift): JsonResponse
    {
        $shift = $this->shiftService->close(
            shift: $shift,
            closer: $request->user(),
            data: CloseShiftData::fromArray($request->validated()),
        );

        return $this->success($shift, 'تم إغلاق الوردية بنجاح');
    }

    /**
     * GET /api/shifts/active — Get the current open shift.
     */
    public function active(): JsonResponse
    {
        $shift = $this->shiftService->getActiveShift();

        if (!$shift) {
            return $this->success(null, 'لا توجد وردية مفتوحة حالياً');
        }

        $shift->load(['opener:id,name', 'drawerSessions.cashier:id,name']);

        return $this->success($shift);
    }

    /**
     * GET /api/shifts/{shift}/summary — Shift close summary.
     */
    public function summary(Shift $shift): JsonResponse
    {
        $summary = $this->shiftService->getCloseSummary($shift);

        return $this->success($summary);
    }
}
