<?php

/**
 * ════════════════════════════════════════════════════════════
 * SERVICE LAYER — USAGE EXAMPLES (NOT a real file to execute)
 * Drop this in your controller / action class as a reference.
 * ════════════════════════════════════════════════════════════
 */

// ┌─────────────────────────────────────────┐
// │  1. OPEN A SHIFT (manager action)       │
// └─────────────────────────────────────────┘

use App\DTOs\CloseDrawerData;
use App\DTOs\CloseShiftData;
use App\DTOs\CreateOrderData;
use App\DTOs\AddOrderItemData;
use App\DTOs\OpenDrawerData;
use App\DTOs\OpenShiftData;
use App\DTOs\ProcessPaymentData;
use App\DTOs\CashMovementData;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Services\CashManagementService;
use App\Services\DrawerSessionService;
use App\Services\OrderService;
use App\Services\ShiftService;

// Inject via constructor DI — never `new Service()`
/** @var ShiftService $shiftService */
/** @var DrawerSessionService $drawerService */
/** @var OrderService $orderService */
/** @var CashManagementService $cashService */

// ┌─────────────────────────────────────────┐
// │  1. OPEN SHIFT                          │
// └─────────────────────────────────────────┘

$shift = $shiftService->open(
    opener: auth()->user(),
    data:   OpenShiftData::fromArray(['notes' => 'وردية الصباح']),
);

// Check if we can close (no open drawers):
$summary = $shiftService->getCloseSummary($shift);
// $summary['can_close'] === false → show which drawers are still open

// ┌─────────────────────────────────────────┐
// │  2. OPEN DRAWER (per cashier)           │
// └─────────────────────────────────────────┘

$session = $drawerService->open(
    data: OpenDrawerData::fromArray([
        'cashier_id'      => 5,
        'shift_id'        => $shift->id,
        'pos_device_id'   => 1,
        'opening_balance' => 200.00,
        'opened_by'       => auth()->id(),  // manager opening for cashier
    ]),
);
// Throws DrawerException::alreadyOpen() if cashier already has one open.
// Guard is enforced at DB level (cashier_active_sessions PK).

// ┌─────────────────────────────────────────┐
// │  3. CREATE ORDER                        │
// └─────────────────────────────────────────┘

$order = $orderService->create(
    cashier: auth()->user(),   // The logged-in cashier
    data: CreateOrderData::fromArray([
        'type'           => 'delivery',
        'source'         => 'pos',
        'customer_id'    => 12,
        'delivery_address' => 'حي النزهة، شارع الأمير محمد',
        'delivery_fee'   => 15.00,
        'tax_rate'       => 15.00,
    ]),
);
// Throws OrderException::noOpenDrawer()  → if cashier has no open drawer
// Throws OrderException::noActiveShift() → if shift is closed
// Throws OrderException::cashierInactive()

// ┌─────────────────────────────────────────┐
// │  4. ADD ITEMS                           │
// └─────────────────────────────────────────┘

$item = $orderService->addItem(
    order: $order,
    data:  AddOrderItemData::fromArray([
        'menu_item_id' => 3,
        'quantity'     => 2,
        'variant_id'   => 7,        // Medium size
        'modifiers'    => [
            14 => 1,  // modifier_id 14 (extra cheese), qty 1
            19 => 2,  // modifier_id 19 (extra sauce),  qty 2
        ],
        'notes' => 'بدون بصل',
    ]),
);
// Price + modifier data is SNAPSHOTTED — future menu changes don't affect this order.
// Order totals are recalculated automatically after each item add.

// ┌─────────────────────────────────────────┐
// │  5. APPLY DISCOUNT                      │
// └─────────────────────────────────────────┘

$order = $orderService->applyDiscount($order, 'percentage', 10.0); // 10% off
// or:
$order = $orderService->applyDiscount($order, 'fixed', 5.00);      // 5 SAR off

// ┌─────────────────────────────────────────┐
// │  6. PROCESS PAYMENT (single)            │
// └─────────────────────────────────────────┘

$order = $orderService->processPayment($order, [
    ProcessPaymentData::fromArray([
        'method' => 'cash',
        'amount' => 100.00,
    ]),
]);
// Cash payments auto-create a CashMovement::Sale in the drawer.
// Order transitions to Confirmed on full payment.

// ┌─────────────────────────────────────────┐
// │  6b. SPLIT PAYMENT                      │
// └─────────────────────────────────────────┘

$order = $orderService->processPayment($order, [
    ProcessPaymentData::fromArray(['method' => 'cash', 'amount' => 50.00]),
    ProcessPaymentData::fromArray(['method' => 'card', 'amount' => 50.00, 'reference_number' => 'TXN-ABC123']),
]);

// ┌─────────────────────────────────────────┐
// │  7. CANCEL ORDER                        │
// └─────────────────────────────────────────┘

$order = $orderService->cancel(
    order:  $order,
    by:     auth()->user(),
    reason: 'طلب العميل إلغاء الطلب',
);
// Reverses cash movements if cash was already taken.
// Throws OrderException::notCancellable() if in final status.

// ┌─────────────────────────────────────────┐
// │  8. MANUAL CASH MOVEMENTS               │
// └─────────────────────────────────────────┘

// Cash IN (top-up):
$movement = $cashService->cashIn($session, CashMovementData::fromArray([
    'amount' => 100.00,
    'notes'  => 'إضافة فكة',
]));

// Cash OUT (safe drop):
$movement = $cashService->cashOut($session, CashMovementData::fromArray([
    'amount' => 500.00,
    'notes'  => 'تحويل إلى الخزينة',
]));
// Throws DrawerException::insufficientBalance() if drawer can't cover it.

// Record a cash expense paid from the drawer:
$expense = $cashService->recordCashExpense(
    session:       $session,
    categoryId:    3,
    amount:        45.00,
    description:   'مواد تنظيف',
    expenseDate:   now()->toDateString(),
    receiptNumber: 'REC-001',
);

// ┌─────────────────────────────────────────┐
// │  9. CLOSE DRAWER                        │
// └─────────────────────────────────────────┘

// Get summary before close:
$summary = $cashService->getDrawerSummary($session);
// $summary['expected_balance'] → show cashier what the system expects

$session = $drawerService->close(
    session: $session,
    data:    CloseDrawerData::fromArray([
        'actual_cash' => 347.50,    // cashier's physical count
        'notes'       => 'تم العد والتسليم',
    ]),
);
// Calculates variance, stores it, releases the guard row.

// ┌─────────────────────────────────────────┐
// │  10. CLOSE SHIFT                        │
// └─────────────────────────────────────────┘

// Get full shift summary (revenue, payment breakdown, variance):
$shiftSummary = $cashService->getShiftSummary($shift);

$shift = $shiftService->close(
    shift:  $shift,
    closer: auth()->user(),
    data:   CloseShiftData::fromArray([
        'actual_cash' => 1250.00,
        'notes'       => 'نهاية وردية الصباح',
    ]),
);
// Throws ShiftException::cannotCloseWithOpenDrawers() if any drawer still open.

// ┌─────────────────────────────────────────┐
// │  11. EXTERNAL ORDER (Talabat)           │
// └─────────────────────────────────────────┘

$externalOrder = $orderService->createExternalOrder(
    processedBy:     auth()->user(),
    data:            CreateOrderData::fromArray([
        'type'                  => 'delivery',
        'source'                => 'talabat',
        'customer_name'         => 'أحمد محمد',
        'customer_phone'        => '0501234567',
        'delivery_address'      => 'حي الروضة',
        'delivery_fee'          => 0,
        'tax_rate'              => 15,
        'external_order_id'     => 'TAL-987654',
        'external_order_number' => '#TLB-987654',
    ]),
    drawerSessionId: $session->id,
);
// External orders arrive as Confirmed + Paid (aggregator collects payment).
// No cashier-drawer prerequisites needed (bypassed intentionally).

// ┌─────────────────────────────────────────┐
// │  12. VARIANCE REPORT                    │
// └─────────────────────────────────────────┘

$flagged = $cashService->getVariantSessions($shift, threshold: 5.00);
// Returns sessions where |variance| > 5.00 SAR, sorted by largest first.
