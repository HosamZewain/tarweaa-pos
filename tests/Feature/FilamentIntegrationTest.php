<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Shift;
use App\Models\PosDevice;
use App\Models\InventoryItem;
use App\Models\CashierDrawerSession;
use App\Models\DiscountLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTerminal;
use App\Models\PosOrderType;
use App\Models\Role;
use App\Enums\OrderType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\OrderItemStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use Filament\Pages\Dashboard;

class FilamentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->first();
        if (!$this->adminUser) {
            $this->adminUser = User::factory()->create([
                'email' => 'admin@pos.com',
                'is_active' => true,
            ]);
        }
        
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator']
        );
        if (!$this->adminUser->roles->contains($adminRole->id)) {
            $this->adminUser->roles()->attach($adminRole->id);
        }
    }

    public function test_admin_can_access_dashboard()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin')
             ->assertSuccessful();
    }

    public function test_admin_can_view_shifts_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/shifts')
             ->assertSuccessful();
    }

    public function test_admin_can_view_drawer_sessions_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/drawer-sessions')
             ->assertSuccessful();
    }

    public function test_admin_can_view_drawer_session_details_with_financial_and_order_sections()
    {
        $shift = Shift::query()->first() ?? Shift::create([
            'shift_number' => 'SHIFT-DRW-VIEW-001',
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::query()->first() ?? PosDevice::create([
            'name' => 'POS Drawer View',
            'identifier' => 'POS-DRW-VIEW-1',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::query()->create([
            'session_number' => 'DRAWER-DETAIL-001',
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->adminUser->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->actingAs($this->adminUser)
            ->get("/admin/drawer-sessions/{$drawer->id}")
            ->assertSuccessful()
            ->assertSee('المؤشرات المالية')
            ->assertSee('الطلبات المرتبطة')
            ->assertSee('الحركات النقدية');
    }

    public function test_admin_can_view_inventory_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/inventory-items')
             ->assertSuccessful();
    }

    public function test_admin_can_view_orders_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/orders')
             ->assertSuccessful();
    }

    public function test_admin_can_view_shift_details_with_financial_and_drawer_sections()
    {
        $shift = Shift::query()->create([
            'shift_number' => 'SHIFT-DETAIL-001',
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::query()->first() ?? PosDevice::create([
            'name' => 'POS Shift View',
            'identifier' => 'POS-SHF-VIEW-1',
            'is_active' => true,
        ]);

        CashierDrawerSession::query()->create([
            'session_number' => 'DRAWER-SHIFT-DETAIL-001',
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->adminUser->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->actingAs($this->adminUser)
            ->get("/admin/shifts/{$shift->id}")
            ->assertSuccessful()
            ->assertSee('الإحصاءات التشغيلية')
            ->assertSee('الملخص المالي')
            ->assertSee('جلسات الدرج ضمن الوردية');
    }

    public function test_admin_can_view_order_details_with_discount_audit_information()
    {
        $shift = Shift::query()->first() ?? Shift::create([
            'shift_number' => 'SHIFT-VIEW-001',
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::query()->first() ?? PosDevice::create([
            'name' => 'POS 1',
            'identifier' => 'POS-1',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::query()->create([
            'session_number' => 'DRAWER-VIEW-001',
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $this->adminUser->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now(),
        ]);

        $category = MenuCategory::query()->first() ?? MenuCategory::create([
            'name' => 'وجبات',
            'is_active' => true,
        ]);

        $item = MenuItem::query()->create([
            'category_id' => $category->id,
            'name' => 'برجر',
            'type' => 'simple',
            'base_price' => 120,
            'is_available' => true,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'ORD-VIEW-DISCOUNT-001',
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'source' => OrderSource::Pos,
            'cashier_id' => $this->adminUser->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'subtotal' => 120,
            'discount_type' => 'fixed',
            'discount_value' => 15,
            'discount_amount' => 15,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 105,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'item_name' => 'برجر',
            'unit_price' => 120,
            'cost_price' => 40,
            'quantity' => 1,
            'discount_amount' => 0,
            'total' => 120,
            'status' => OrderItemStatus::Pending,
        ]);

        DiscountLog::query()->create([
            'order_id' => $order->id,
            'applied_by' => $this->adminUser->id,
            'requested_by' => $this->adminUser->id,
            'scope' => 'order',
            'action' => 'applied',
            'discount_type' => 'fixed',
            'discount_value' => 15,
            'discount_amount' => 15,
            'reason' => 'خصم رضا عميل',
        ]);

        $this->actingAs($this->adminUser)
            ->get("/admin/orders/{$order->id}")
            ->assertSuccessful()
            ->assertSee('تفاصيل الخصم')
            ->assertSee('خصم رضا عميل')
            ->assertSee($this->adminUser->name);
    }

    public function test_admin_can_view_users_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/users')
             ->assertSuccessful();
    }

    public function test_admin_can_view_roles_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/roles')
             ->assertSuccessful();
    }

    public function test_admin_can_view_suppliers_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/suppliers')
             ->assertSuccessful();
    }

    public function test_admin_can_view_purchases_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/purchases')
             ->assertSuccessful();
    }

    public function test_admin_can_view_expenses_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/expenses')
             ->assertSuccessful();
    }

    public function test_admin_can_view_expense_categories_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/expense-categories')
             ->assertSuccessful();
    }

    public function test_admin_can_view_pos_devices_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/pos-devices')
             ->assertSuccessful();
    }

    public function test_admin_can_view_payment_terminals_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/payment-terminals')
             ->assertSuccessful();
    }

    public function test_admin_can_view_database_backups_page()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/database-backups-page')
             ->assertSuccessful()
             ->assertSee('النسخ الاحتياطية والاستعادة')
             ->assertSee('إعادة تهيئة البيانات التشغيلية');
    }

    public function test_admin_can_view_menu_categories_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/menu-categories')
             ->assertSuccessful();
    }

    public function test_admin_can_view_menu_items_resource()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/menu-items')
             ->assertSuccessful();
    }

    public function test_admin_can_edit_menu_item_page()
    {
        $category = MenuCategory::query()->first() ?? MenuCategory::create([
            'name' => 'مشروبات',
            'is_active' => true,
        ]);

        $menuItem = MenuItem::query()->create([
            'category_id' => $category->id,
            'name' => 'قهوة',
            'type' => 'simple',
            'base_price' => 35,
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->adminUser)
             ->get("/admin/menu-items/{$menuItem->id}/edit")
             ->assertSuccessful();
    }

    public function test_admin_can_view_sales_report_page()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/sales-report')
             ->assertSuccessful();
    }

    public function test_admin_can_view_discounts_report_page()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/discounts-report')
             ->assertSuccessful();
    }

    public function test_admin_can_view_card_terminal_report_page()
    {
        PaymentTerminal::create([
            'name' => 'CIB Main',
            'bank_name' => 'CIB',
            'code' => 'CIB-FILAMENT-1',
            'fee_type' => 'percentage',
            'fee_percentage' => 2.5,
            'fee_fixed_amount' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser)
             ->get('/admin/card-terminal-report')
             ->assertSuccessful();
    }

    public function test_admin_can_view_drawer_reconciliation_report_page()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/drawer-reconciliation-report')
             ->assertSuccessful();
    }

    public function test_admin_can_view_inventory_report_page()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/inventory-report')
             ->assertSuccessful();
    }
}
