<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Counter\HandoverCounterOrderRequest;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterController extends Controller
{
    public function __construct(
        private readonly OrderLifecycleService $orderLifecycleService,
    ) {}

    public function orders(Request $request, string $lane): JsonResponse
    {
        abort_unless(in_array($lane, ['odd', 'even'], true), 404);

        if (!$request->user()?->canAccessCounterSurface()) {
            return $this->error('ليس لديك صلاحية شاشة التسليم والاستلام.', 403);
        }

        $orders = Order::query()
            ->with([
                'customer:id,name',
                'cashier:id,name',
                'items:id,order_id,item_name,variant_name,quantity',
            ])
            ->counterVisible()
            ->forCounterLane($lane)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $this->success([
            'lane' => $lane,
            'orders' => $orders,
            'stats' => [
                'total' => $orders->count(),
                'ready' => $orders->filter(fn (Order $order) => $order->status->value === 'ready')->count(),
                'preparing' => $orders->filter(fn (Order $order) => $order->status->value === 'preparing')->count(),
                'confirmed' => $orders->filter(fn (Order $order) => $order->status->value === 'confirmed')->count(),
            ],
        ]);
    }

    public function handover(HandoverCounterOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orderLifecycleService->markHandedOver($order, $request->user());

        return $this->success($order, 'تم تسليم الطلب بنجاح');
    }
}
