<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Services\DrawerSessionService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class POSController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly DrawerSessionService $drawerService,
    ) {}

    /**
     * GET /api/pos/status — POS readiness status for the current cashier.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeShift   = $this->shiftService->getActiveShift();
        $activeDrawer  = $this->drawerService->getActiveSessionForCashier($user->id);

        return $this->success([
            'ready'          => $user->canCreateOrder(),
            'block_reason'   => $user->getOrderBlockReason(),
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
            ] : null,
        ]);
    }

    /**
     * GET /api/pos/menu — Full menu for POS display.
     */
    public function menu(): JsonResponse
    {
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
        $types = PosOrderType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'type', 'source']);

        return $this->success($types);
    }
}
