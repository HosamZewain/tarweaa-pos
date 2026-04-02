<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AuthorizeDiscountApprovalRequest;
use App\Http\Requests\Order\ListSettlementUsersRequest;
use App\Http\Requests\Order\PreviewOrderSettlementRequest;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\PaymentTerminal;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\User;
use App\Services\ChannelPricingService;
use App\Services\DiscountApprovalService;
use App\Services\DrawerSessionService;
use App\Services\ManagerVerificationService;
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
        private readonly ManagerVerificationService $managerVerificationService,
        private readonly PaymentTerminalFeeService $paymentTerminalFeeService,
        private readonly ChannelPricingService $channelPricingService,
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
    public function menu(Request $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        $selectedOrderType = $this->posOrderTypeService->resolveForOrderCreation(
            $request->integer('pos_order_type_id') ?: null,
        );

        $categories = MenuCategory::with([
            'menuItems' => function ($query) {
                $query->where('is_active', true)
                      ->where('is_available', true)
                      ->orderBy('sort_order')
                      ->with([
                          'variants' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order'),
                          'channelPrices',
                          'modifierGroups' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')
                              ->with(['modifiers' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order')]),
                      ]);
            },
        ])
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success(
            $categories->map(fn (MenuCategory $category) => $this->transformCategoryForPos($category, $selectedOrderType))->values(),
        );
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
            ->get(['id', 'name', 'type', 'source', 'pricing_rule_type', 'pricing_rule_value', 'is_default', 'sort_order'])
            ->map(fn (PosOrderType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'type' => $type->type,
                'source' => $type->source,
                'pricing_rule_type' => $type->pricing_rule_type?->value ?? $type->getRawOriginal('pricing_rule_type'),
                'pricing_rule_value' => $type->pricing_rule_value,
                'is_default' => (bool) $type->is_default,
                'sort_order' => $type->sort_order,
                'contextual_payment_method' => $this->posOrderTypeService->contextualPaymentMethod($type),
            ])
            ->values();

        return $this->success($types);
    }

    private function transformCategoryForPos(MenuCategory $category, ?PosOrderType $selectedOrderType): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'image' => $category->image,
            'sort_order' => $category->sort_order,
            'menu_items' => $category->menuItems
                ->map(fn (MenuItem $item) => $this->transformMenuItemForPos($item, $selectedOrderType))
                ->values()
                ->all(),
        ];
    }

    private function transformMenuItemForPos(MenuItem $item, ?PosOrderType $selectedOrderType): array
    {
        return [
            'id' => $item->id,
            'category_id' => $item->category_id,
            'name' => $item->name,
            'description' => $item->description,
            'sku' => $item->sku,
            'image' => $item->image,
            'type' => $item->type,
            'base_price' => (float) $item->base_price,
            'price' => $this->channelPricingService->resolvePrice($item, null, $selectedOrderType),
            'cost_price' => (float) ($item->cost_price ?? 0),
            'preparation_time' => $item->preparation_time,
            'track_inventory' => (bool) $item->track_inventory,
            'is_available' => (bool) $item->is_available,
            'is_active' => (bool) $item->is_active,
            'sort_order' => $item->sort_order,
            'variants' => $item->variants
                ->map(fn (MenuItemVariant $variant) => $this->transformVariantForPos($item, $variant, $selectedOrderType))
                ->values()
                ->all(),
            'modifier_groups' => $item->modifierGroups
                ->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'selection_type' => $group->selection_type,
                    'is_required' => (bool) $group->is_required,
                    'min_selections' => $group->min_selections,
                    'max_selections' => $group->max_selections,
                    'sort_order' => $group->pivot?->sort_order,
                    'modifiers' => $group->modifiers->map(fn ($modifier) => [
                        'id' => $modifier->id,
                        'name' => $modifier->name,
                        'price' => (float) $modifier->price,
                        'sort_order' => $modifier->sort_order,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function transformVariantForPos(
        MenuItem $item,
        MenuItemVariant $variant,
        ?PosOrderType $selectedOrderType,
    ): array {
        return [
            'id' => $variant->id,
            'menu_item_id' => $variant->menu_item_id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'base_price' => (float) $variant->price,
            'price' => $this->channelPricingService->resolvePrice($item, $variant, $selectedOrderType),
            'cost_price' => (float) ($variant->cost_price ?? 0),
            'is_available' => (bool) $variant->is_available,
            'sort_order' => $variant->sort_order,
        ];
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
     * GET /api/pos/manager-approvers — Active managers/admins who can approve sensitive POS actions.
     */
    public function managerApprovers(Request $request): JsonResponse
    {
        if ($response = $this->authorizePosAccess($request)) {
            return $response;
        }

        return $this->success($this->managerVerificationService->listApprovers());
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
