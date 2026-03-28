<?php

namespace App\Http\Controllers\Api;

use App\DTOs\AddOrderItemData;
use App\DTOs\CreateOrderData;
use App\DTOs\ProcessPaymentData;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AddItemRequest;
use App\Http\Requests\Order\ApplyDiscountRequest;
use App\Http\Requests\Order\ApplyOrderSettlementRequest;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\CreateExternalOrderRequest;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\ProcessPaymentRequest;
use App\Http\Requests\Order\RefundOrderRequest;
use App\Http\Requests\Order\TransitionOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\DiscountApprovalService;
use App\Services\OrderCreationService;
use App\Services\OrderSettlementService;
use App\Services\OrderPaymentService;
use App\Services\OrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
        private readonly OrderPaymentService $orderPaymentService,
        private readonly OrderSettlementService $orderSettlementService,
        private readonly OrderLifecycleService $orderLifecycleService,
        private readonly DiscountApprovalService $discountApprovalService,
    ) {}

    /**
     * GET /api/orders — Paginated orders with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['cashier:id,name', 'customer:id,name,phone', 'items.modifiers'])
            ->when($request->status, function ($q, $status) {
                if (str_contains($status, ',')) {
                    return $q->whereIn('status', explode(',', $status));
                }
                return $q->where('status', $status);
            })
            ->when($request->shift_id, fn ($q, $shiftId) => $q->where('shift_id', $shiftId))
            ->when($request->drawer_session_id, fn ($q, $sessionId) => $q->where('drawer_session_id', $sessionId))
            ->when($request->source, fn ($q, $source) => $q->where('source', $source))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginated($orders);
    }

    /**
     * GET /api/orders/{order} — Single order with all details.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'items.modifiers',
            'payments.terminal:id,name,bank_name,code',
            'cashier:id,name',
            'customer:id,name,phone',
            'shift:id,shift_number',
            'drawerSession:id,session_number',
            'posDevice:id,name',
            'settlement.lines.orderItem:id,order_id,item_name,quantity,total',
            'settlement.lines.menuItem:id,name',
            'settlement.beneficiaryUser:id,name',
            'settlement.chargeAccountUser:id,name',
        ]);

        return $this->success($order);
    }

    /**
     * POST /api/orders — Create a new POS order.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderCreationService->create(
            cashier: $request->user(),
            data: CreateOrderData::fromArray($request->validated()),
        );

        return $this->created($order, 'تم إنشاء الطلب بنجاح');
    }

    /**
     * POST /api/orders/{order}/items — Add an item to an order.
     */
    public function addItem(AddItemRequest $request, Order $order): JsonResponse
    {
        $item = $this->orderCreationService->addItem(
            order: $order,
            data: AddOrderItemData::fromArray($request->validated()),
            actorId: $request->user()->id,
        );

        return $this->created([
            'item'  => $item,
            'order' => $order->fresh(),
        ], 'تم إضافة المنتج بنجاح');
    }

    /**
     * DELETE /api/orders/items/{item} — Remove (cancel) an order item.
     */
    public function removeItem(OrderItem $item): JsonResponse
    {
        $this->orderLifecycleService->removeItem($item, request()->user());

        return $this->success(
            $item->order->fresh(),
            'تم إلغاء المنتج بنجاح'
        );
    }

    /**
     * POST /api/orders/{order}/discount — Apply or update a discount.
     */
    public function applyDiscount(ApplyDiscountRequest $request, Order $order): JsonResponse
    {
        if (!$request->user()->hasPermission('apply_discount')) {
            return $this->error('ليس لديك صلاحية لتطبيق الخصم.', 403);
        }

        $approvedBy = $this->discountApprovalService->consume(
            requestedBy: $request->user(),
            token: $request->validated('approval_token'),
            type: $request->validated('type'),
            value: (float) $request->validated('value'),
            reason: $request->validated('reason'),
        );

        $order = $this->orderLifecycleService->applyDiscount(
            order: $order,
            by: $approvedBy,
            type: $request->validated('type'),
            value: (float) $request->validated('value'),
            requestedBy: $request->user(),
            reason: $request->validated('reason'),
        );

        return $this->success($order, 'تم تطبيق الخصم بنجاح');
    }

    /**
     * POST /api/orders/{order}/pay — Process payment(s).
     */
    public function processPayment(ProcessPaymentRequest $request, Order $order): JsonResponse
    {
        $payments = ProcessPaymentData::collectionFromArray(
            $request->validated('payments')
        );

        $order = $this->orderPaymentService->processPayment($order, $payments, $request->user()->id);

        return $this->success($order, 'تم الدفع بنجاح');
    }

    public function applySettlement(ApplyOrderSettlementRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        $scenario = $validated['scenario'];

        $order = match ($scenario) {
            'owner_charge' => $this->orderSettlementService->applyOwnerCharge(
                order: $order,
                chargeAccount: \App\Models\User::findOrFail((int) $validated['charge_account_user_id']),
                actorId: $request->user()->id,
                notes: $validated['notes'] ?? null,
            ),
            'employee_allowance' => $this->orderSettlementService->applyEmployeeMonthlyAllowance(
                order: $order,
                employee: \App\Models\User::findOrFail((int) $validated['user_id']),
                actorId: $request->user()->id,
                notes: $validated['notes'] ?? null,
            ),
            'employee_free_meal' => $this->orderSettlementService->applyEmployeeFreeMealBenefit(
                order: $order,
                employee: \App\Models\User::findOrFail((int) $validated['user_id']),
                actorId: $request->user()->id,
                notes: $validated['notes'] ?? null,
            ),
        };

        return $this->success($order, 'تم تطبيق تسوية الطلب بنجاح');
    }

    /**
     * POST /api/orders/{order}/cancel — Cancel an order.
     */
    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orderLifecycleService->cancel(
            order: $order,
            by: $request->user(),
            reason: $request->validated('reason'),
        );

        return $this->success($order, 'تم إلغاء الطلب بنجاح');
    }

    /**
     * POST /api/orders/{order}/refund — Refund an order.
     */
    public function refund(RefundOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orderPaymentService->refund(
            order: $order,
            by: $request->user(),
            refundAmount: (float) $request->validated('amount'),
            reason: $request->validated('reason'),
        );

        return $this->success($order, 'تم استرجاع الطلب بنجاح');
    }

    /**
     * PATCH /api/orders/{order}/status — Transition order status.
     */
    public function transition(TransitionOrderRequest $request, Order $order): JsonResponse
    {
        $newStatus = OrderStatus::from($request->validated('status'));

        $order = $this->orderLifecycleService->transition(
            order: $order,
            newStatus: $newStatus,
            by: $request->user(),
        );

        return $this->success($order, 'تم تحديث حالة الطلب بنجاح');
    }

    /**
     * POST /api/orders/external — Create an external aggregator order.
     */
    public function storeExternal(CreateExternalOrderRequest $request): JsonResponse
    {
        $order = $this->orderCreationService->createExternalOrder(
            processedBy: $request->user(),
            data: CreateOrderData::fromArray($request->validated()),
            drawerSessionId: (int) $request->validated('drawer_session_id'),
        );

        return $this->created($order, 'تم إنشاء الطلب الخارجي بنجاح');
    }
}
