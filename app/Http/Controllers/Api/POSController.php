<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AuthorizeDiscountApprovalRequest;
use App\Http\Requests\Order\ListSettlementUsersRequest;
use App\Http\Requests\Order\PreviewOrderSettlementRequest;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\PaymentTerminal;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\User;
use App\Services\DiscountApprovalService;
use App\Services\DrawerSessionService;
use App\Services\PaymentTerminalFeeService;
use App\Services\PosOrderTypeService;
use App\Services\PosSettlementPreviewService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class POSController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly DrawerSessionService $drawerService,
        private readonly DiscountApprovalService $discountApprovalService,
        private readonly PaymentTerminalFeeService $paymentTerminalFeeService,
        private readonly PosOrderTypeService $posOrderTypeService,
        private readonly PosSettlementPreviewService $posSettlementPreviewService,
    ) {}

    private function authorizePosAccess(Request $request): ?JsonResponse
    {
        if ($request->user()->canAccessPosSurface()) {
            return null;
        }

        return $this->error('ليس لديك صلاحية للوصول إلى نقطة البيع.', 403);
    }

    /**
     * GET /api/pos/status — POS readiness status for the current cashier.
     */
    public function status(Request $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        $user = $request->user();

        $activeShift   = $this->shiftService->getActiveShift();
        $activeDrawer  = $this->drawerService->getActiveSessionForCashier($user->id);
        $reconciliationLock = $activeDrawer
            ? $this->drawerService->getCloseReconciliationState($activeDrawer, $user)
            : null;
        $isLocked = $reconciliationLock !== null;
        $ready = $user->canCreateOrder() && !$isLocked;
        $blockReason = $isLocked
            ? 'تم بدء جرد إغلاق الدرج بالفعل. يجب إكمال الإغلاق أولاً قبل العودة إلى نقطة البيع.'
            : $user->getOrderBlockReason();

        return $this->success([
            'ready'          => $ready,
            'block_reason'   => $blockReason,
            'cashier'        => [
                'id'   => $user->id,
                'name' => $user->name,
            ],
            'shift'          => $activeShift ? [
                'id'           => $activeShift->id,
                'shift_number' => $activeShift->shift_number,
                'started_at'   => $activeShift->started_at,
            ] : null,
            'drawer_session' => $activeDrawer ? [
                'id'              => $activeDrawer->id,
                'session_number'  => $activeDrawer->session_number,
                'opening_balance' => $activeDrawer->opening_balance,
                'started_at'      => $activeDrawer->started_at,
                'close_reconciliation' => $reconciliationLock,
            ] : null,
        ]);
    }

    /**
     * GET /api/pos/menu — Full menu for POS display.
     */
    public function menu(): JsonResponse
    {
        if ($response = $this->authorizePosAccess(request())) {
            return $response;
        }

        $categories = MenuCategory::with([
            'menuItems' => function ($query) {
                $query->where('is_active', true)
                      ->where('is_available', true)
                      ->orderBy('sort_order')
                      ->with([
                          'variants' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order'),
                          'modifierGroups' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')
                              ->with(['modifiers' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order')]),
                      ]);
            },
        ])
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success($categories);
    }

    /**
     * GET /api/pos/customers — Search customers for POS.
     */
    public function customers(Request $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        $query = Customer::query()->where('is_active', true);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')
                           ->limit(20)
                           ->get(['id', 'name', 'phone', 'email', 'address', 'loyalty_points', 'total_orders']);

        return $this->success($customers);
    }

    /**
     * POST /api/pos/customers — Quick customer creation from POS.
     */
    public function customerStore(Request $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => ['required', 'string', 'max:20', 'unique:customers,phone'],
            'email'   => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required'  => 'اسم العميل مطلوب.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique'   => 'رقم الهاتف مسجل مسبقاً.',
        ]);

        $customer = Customer::create(array_merge(
            $validated,
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        ));

        return $this->created($customer, 'تم إضافة العميل بنجاح');
    }

    /**
     * GET /api/pos/devices — List active POS devices.
     */
    public function devices(): JsonResponse
    {
        if ($response = $this->authorizePosAccess(request())) {
            return $response;
        }

        $devices = PosDevice::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'identifier', 'location']);

        return $this->success($devices);
    }

    /**
     * GET /api/pos/order-types — Configurable order types for POS.
     */
    public function orderTypes(): JsonResponse
    {
        if ($response = $this->authorizePosAccess(request())) {
            return $response;
        }

        $types = $this->posOrderTypeService->activeQuery()
            ->get(['id', 'name', 'type', 'source', 'is_default', 'sort_order']);

        return $this->success($types);
    }

    /**
     * GET /api/pos/payment-terminals — Active card terminals for POS.
     */
    public function paymentTerminals(Request $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        $terminals = PaymentTerminal::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'bank_name',
                'code',
                'fee_type',
                'fee_percentage',
                'fee_fixed_amount',
            ]);

        return $this->success($terminals);
    }

    /**
     * POST /api/pos/payment-preview — Backend fee preview for card payments.
     */
    public function paymentPreview(Request $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        $validated = $request->validate([
            'method' => ['required', 'in:' . implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'terminal_id' => ['nullable', 'integer', 'exists:payment_terminals,id'],
        ], [
            'method.required' => 'طريقة الدفع مطلوبة.',
            'amount.required' => 'مبلغ الدفع مطلوب.',
            'amount.gt' => 'مبلغ الدفع يجب أن يكون أكبر من صفر.',
            'terminal_id.exists' => 'جهاز الدفع الإلكتروني المحدد غير موجود.',
        ]);

        $amount = round((float) $validated['amount'], 2);

        if ($validated['method'] !== PaymentMethod::Card->value) {
            return $this->success([
                'paid_amount' => $amount,
                'fee_amount' => 0,
                'net_settlement_amount' => $amount,
                'terminal' => null,
            ]);
        }

        $terminal = $this->paymentTerminalFeeService->getActiveTerminalOrFail(
            isset($validated['terminal_id']) ? (int) $validated['terminal_id'] : null
        );

        $preview = $this->paymentTerminalFeeService->calculate($terminal, $amount);

        return $this->success([
            'paid_amount' => $amount,
            'fee_amount' => $preview['fee_amount'],
            'net_settlement_amount' => $preview['net_settlement_amount'],
            'terminal' => [
                'id' => $terminal->id,
                'name' => $terminal->name,
                'bank_name' => $terminal->bank_name,
                'code' => $terminal->code,
            ],
        ]);
    }

    /**
     * GET /api/pos/settlement-users — Active candidates for special settlement modes.
     */
    public function settlementUsers(ListSettlementUsersRequest $request): JsonResponse
    {
        return $this->success(
            $this->posSettlementPreviewService->listCandidates(
                scenario: $request->validated('scenario'),
                search: $request->validated('search'),
            )
        );
    }

    /**
     * POST /api/pos/settlement-preview — Backend-evaluated preview for special settlement coverage.
     */
    public function settlementPreview(PreviewOrderSettlementRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $preview = $this->posSettlementPreviewService->preview(
            scenario: $validated['scenario'],
            items: $validated['items'],
            beneficiary: isset($validated['user_id']) ? User::find((int) $validated['user_id']) : null,
            chargeAccount: isset($validated['charge_account_user_id']) ? User::find((int) $validated['charge_account_user_id']) : null,
            discountType: $validated['discount_type'] ?? null,
            discountValue: (float) ($validated['discount_value'] ?? 0),
            taxRate: (float) ($validated['tax_rate'] ?? 0),
            deliveryFee: (float) ($validated['delivery_fee'] ?? 0),
        );

        return $this->success($preview);
    }

    /**
     * GET /api/pos/discount-approvers — Active managers/admins who can approve discounts.
     */
    public function discountApprovers(Request $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        if (!$request->user()->hasPermission('apply_discount')) {
            return $this->error('ليس لديك صلاحية لطلب خصم.', 403);
        }

        return $this->success($this->discountApprovalService->listApprovers());
    }

    /**
     * POST /api/pos/discount-approval — Validate manager approval and issue a short-lived token.
     */
    public function authorizeDiscount(AuthorizeDiscountApprovalRequest $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        $approval = $this->discountApprovalService->authorize(
            requestedBy: $request->user(),
            approverId: (int) $request->validated('approver_id'),
            approverPin: $request->validated('approver_pin'),
            type: $request->validated('type'),
            value: (float) $request->validated('value'),
            reason: $request->validated('reason'),
        );

        return $this->success([
            'approval_token' => $approval['token'],
            'expires_in_seconds' => $approval['expires_in_seconds'],
            'approver' => [
                'id' => $approval['approver']->id,
                'name' => $approval['approver']->name,
                'username' => $approval['approver']->username,
            ],
        ], 'تم اعتماد الخصم من المدير.');
    }
}
