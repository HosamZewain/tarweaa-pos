<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierDrawerSession;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosDevice;
use App\Models\Shift;
use App\Models\User;
use App\Services\OrderLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_order_discount_creates_discount_log(): void
    {
        $user = User::factory()->create([
            'name' => 'Discount User',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHF-DISCOUNT-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $user->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'Discount POS',
            'identifier' => 'POS-DISCOUNT-001',
            'is_active' => true,
        ]);

        $drawerSession = CashierDrawerSession::create([
            'session_number' => 'DRW-DISCOUNT-001',
            'cashier_id' => $user->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $user->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        $category = MenuCategory::create([
            'name' => 'Main',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Burger',
            'type' => 'simple',
            'base_price' => 100,
            'cost_price' => 40,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-DISCOUNT-001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $user->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawerSession->id,
            'pos_device_id' => $device->id,
            'customer_name' => 'عميل الخصم',
            'customer_phone' => '01000000000',
            'subtotal' => 100,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 100,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'Burger',
            'unit_price' => 100,
            'cost_price' => 40,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => 100,
            'status' => OrderItemStatus::Pending,
        ]);

        $order = app(OrderLifecycleService::class)->applyDiscount($order, $user, 'fixed', 10);

        $this->assertSame('fixed', $order->discount_type);
        $this->assertSame('10.00', $order->discount_amount);

        $this->assertDatabaseHas('discount_logs', [
            'order_id' => $order->id,
            'applied_by' => $user->id,
            'scope' => 'order',
            'action' => 'applied',
            'discount_type' => 'fixed',
            'discount_amount' => '10.00',
        ]);
    }
}
