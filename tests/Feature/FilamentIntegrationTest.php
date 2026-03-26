<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Shift;
use App\Models\PosDevice;
use App\Models\InventoryItem;
use App\Models\CashierDrawerSession;
use App\Models\Role;
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

    public function test_admin_can_view_sales_report_page()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/sales-report')
             ->assertSuccessful();
    }

    public function test_admin_can_view_inventory_report_page()
    {
        $this->actingAs($this->adminUser)
             ->get('/admin/inventory-report')
             ->assertSuccessful();
    }
}
