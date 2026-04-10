<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * GET /api/inventory — Paginated inventory items.
     */
    public function index(Request $request): JsonResponse
    {
        $items = InventoryItem::query()
            ->when($request->category, fn ($q, $cat) => $q->where('category', $cat))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('low_stock'), function ($q) {
                $q->whereColumn('current_stock', '<=', 'minimum_stock');
            })
            ->with('defaultSupplier:id,name')
            ->orderBy('name')
            ->paginate($request->get('per_page', 25));

        return $this->paginated($items);
    }

    /**
     * GET /api/inventory/{item} — Single inventory item with recent transactions.
     */
    public function show(InventoryItem $item): JsonResponse
    {
        $item->load('defaultSupplier:id,name');

        $recentTransactions = $item->transactions()
            ->with('performer:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return $this->success([
            'item'                 => $item,
            'recent_transactions'  => $recentTransactions,
        ]);
    }

    /**
     * GET /api/inventory/low-stock — Items below minimum stock threshold.
     */
    public function lowStock(): JsonResponse
    {
        $items = InventoryItem::where('is_active', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->with('defaultSupplier:id,name')
            ->orderByRaw('current_stock - minimum_stock ASC')
            ->get()
            ->map(fn ($item) => [
                'id'            => $item->id,
                'name'          => $item->name,
                'sku'           => $item->sku,
                'category'      => $item->category,
                'unit'          => $item->unit,
                'current_stock' => (float) $item->current_stock,
                'minimum_stock' => (float) $item->minimum_stock,
                'deficit'       => round((float) $item->minimum_stock - (float) $item->current_stock, 3),
                'supplier'      => $item->defaultSupplier?->name,
            ]);

        return $this->success($items);
    }

    /**
     * POST /api/inventory/{item}/adjust — Disabled unsafe direct adjustment path.
     */
    public function adjust(Request $request, InventoryItem $item): JsonResponse
    {
        return $this->error(
            'تم إيقاف التعديل المباشر للمخزون. استخدم جرد الموقع أو استلام المشتريات أو التحويلات المعتمدة فقط.',
            403
        );
    }

    /**
     * GET /api/inventory/{item}/transactions — Transaction log for an item.
     */
    public function transactions(Request $request, InventoryItem $item): JsonResponse
    {
        $transactions = $item->transactions()
            ->with('performer:id,name')
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 25));

        return $this->paginated($transactions);
    }
}
