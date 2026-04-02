<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CounterController;
use App\Http\Controllers\Api\DrawerController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MealBenefitController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\POSController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Tarweaa POS — RESTful API Routes
| All routes return JSON responses.
|
*/

// ─────────────────────────────────────────────────────────────────────────
// PUBLIC (no auth required)
// ─────────────────────────────────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/login',     [AuthController::class, 'login']);
    Route::post('/pin-login', [AuthController::class, 'pinLogin']);
});

// ─────────────────────────────────────────────────────────────────────────
// PROTECTED (auth:sanctum)
// ─────────────────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // ── POS ──────────────────────────────────────────────────────────────
    Route::prefix('pos')->group(function () {
        Route::get('/status',    [POSController::class, 'status']);
        Route::get('/menu',      [POSController::class, 'menu']);
        Route::get('/customers', [POSController::class, 'customers']);
        Route::post('/customers', [POSController::class, 'customerStore']);
        Route::get('/devices',   [POSController::class, 'devices']);
        Route::get('/order-types', [POSController::class, 'orderTypes']);
        Route::get('/payment-terminals', [POSController::class, 'paymentTerminals']);
        Route::post('/payment-preview', [POSController::class, 'paymentPreview']);
        Route::get('/settlement-users', [POSController::class, 'settlementUsers']);
        Route::post('/settlement-preview', [POSController::class, 'settlementPreview']);
        Route::get('/manager-approvers', [POSController::class, 'managerApprovers']);
        Route::get('/discount-approvers', [POSController::class, 'discountApprovers']);
        Route::post('/discount-approval', [POSController::class, 'authorizeDiscount']);
    });

    Route::get('/meal-benefits/users/{user}/summary', [MealBenefitController::class, 'summary']);

    // ── Shifts ───────────────────────────────────────────────────────────
    Route::prefix('shifts')->group(function () {
        Route::get('/',                [ShiftController::class, 'index']);
        Route::get('/active',          [ShiftController::class, 'active']);
        Route::post('/open',           [ShiftController::class, 'open']);
        Route::get('/{shift}',         [ShiftController::class, 'show']);
        Route::post('/{shift}/close',  [ShiftController::class, 'close']);
        Route::get('/{shift}/summary', [ShiftController::class, 'summary']);
    });

    // ── Drawers ──────────────────────────────────────────────────────────
    Route::prefix('drawers')->group(function () {
        Route::get('/active',               [DrawerController::class, 'active']);
        Route::post('/open',                [DrawerController::class, 'open']);
        Route::post('/{session}/close-preview', [DrawerController::class, 'previewClose']);
        Route::post('/{session}/close',     [DrawerController::class, 'close']);
        Route::get('/{session}/summary',    [DrawerController::class, 'summary']);
        Route::post('/{session}/cash-in',   [DrawerController::class, 'cashIn']);
        Route::post('/{session}/cash-out',  [DrawerController::class, 'cashOut']);
    });

    // ── Orders ───────────────────────────────────────────────────────────
    Route::prefix('orders')->group(function () {
        Route::get('/',                    [OrderController::class, 'index']);
        Route::post('/',                   [OrderController::class, 'store']);
        Route::post('/external',           [OrderController::class, 'storeExternal']);
        Route::get('/{order}',             [OrderController::class, 'show']);
        Route::post('/{order}/items',      [OrderController::class, 'addItem']);
        Route::delete('/items/{item}',     [OrderController::class, 'removeItem']);
        Route::post('/{order}/discount',   [OrderController::class, 'applyDiscount']);
        Route::post('/{order}/settlement', [OrderController::class, 'applySettlement']);
        Route::post('/{order}/pay',        [OrderController::class, 'processPayment']);
        Route::post('/{order}/cancel',     [OrderController::class, 'cancel']);
        Route::post('/{order}/refund',     [OrderController::class, 'refund']);
        Route::patch('/{order}/status',    [OrderController::class, 'transition']);
    });

    // ── Counter Screen ───────────────────────────────────────────────────
    Route::prefix('counter')->group(function () {
        Route::get('/orders/{lane}', [CounterController::class, 'orders'])
            ->whereIn('lane', ['all', 'odd', 'even']);
        Route::post('/orders/{order}/handover', [CounterController::class, 'handover']);
    });

    // ── Inventory ────────────────────────────────────────────────────────
    Route::prefix('inventory')->group(function () {
        Route::get('/',                          [InventoryController::class, 'index']);
        Route::get('/low-stock',                 [InventoryController::class, 'lowStock']);
        Route::get('/{item}',                    [InventoryController::class, 'show']);
        Route::post('/{item}/adjust',            [InventoryController::class, 'adjust']);
        Route::get('/{item}/transactions',       [InventoryController::class, 'transactions']);
    });

    // ── Reports ──────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        // Sales
        Route::get('/sales-by-item',           [ReportController::class, 'salesByItem']);
        Route::get('/sales-by-category',       [ReportController::class, 'salesByCategory']);
        Route::get('/sales-by-payment-method', [ReportController::class, 'salesByPaymentMethod']);
        Route::get('/card-payments-by-terminal', [ReportController::class, 'cardPaymentsByTerminal']);
        Route::get('/daily-sales',             [ReportController::class, 'dailySales']);
        
        // Drawers & Shifts
        Route::get('/drawers-reconciliation',  [ReportController::class, 'drawersReconciliation']);
        Route::get('/drawers/{session}',       [ReportController::class, 'drawerSummary']);
        Route::get('/cash-variance/{shift}',   [ReportController::class, 'cashVariance']);
        Route::get('/shifts/{shift}',          [ReportController::class, 'shiftSummary']);
        
        // Inventory
        Route::get('/inventory-valuation',     [ReportController::class, 'inventoryValuation']);
        Route::get('/inventory-movements',     [ReportController::class, 'inventoryMovements']);

        // Expenses
        Route::get('/expenses',                [ReportController::class, 'expenses']);
        Route::post('/expenses',               [ReportController::class, 'recordExpense']);
        Route::get('/expense-categories',      [ReportController::class, 'expenseCategories']);
    });
});
