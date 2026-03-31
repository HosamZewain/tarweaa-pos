<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\SystemPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Role $managerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'username' => 'manager-user',
            'is_active' => true,
        ]);

        $this->managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            ['display_name' => 'Manager'],
        );

        $this->manager->roles()->sync([$this->managerRole->id]);
    }

    public function test_manager_without_user_permission_cannot_access_users_resource(): void
    {
        $this->actingAs($this->manager)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_manager_with_user_view_permission_still_cannot_access_users_resource(): void
    {
        $this->managerRole->givePermissionTo('users.viewAny');

        $this->actingAs($this->manager)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_manager_without_permission_policy_cannot_access_permissions_resource(): void
    {
        $this->actingAs($this->manager)
            ->get('/admin/permissions')
            ->assertForbidden();
    }

    public function test_rbac_permissions_are_seeded(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'users.viewAny']);
        $this->assertDatabaseHas('permissions', ['name' => 'roles.update']);
        $this->assertDatabaseHas('permissions', ['name' => 'permissions.delete']);
        $this->assertDatabaseHas('permissions', ['name' => 'inventory_transactions.viewAny']);
        $this->assertDatabaseHas('permissions', ['name' => 'reports.sales.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'shifts.open']);
    }

    public function test_manager_without_report_permission_cannot_access_sales_report_page(): void
    {
        $this->actingAs($this->manager)
            ->get('/admin/sales-report')
            ->assertForbidden();
    }

    public function test_manager_role_does_not_keep_dashboard_analytics_permission_after_seeding(): void
    {
        $this->assertFalse(
            $this->managerRole->fresh()->permissions()->where('name', 'dashboard.analytics.view')->exists()
        );
    }

    public function test_manager_with_report_permission_can_access_sales_report_page(): void
    {
        $this->managerRole->givePermissionTo('reports.sales.view');

        $this->actingAs($this->manager)
            ->get('/admin/sales-report')
            ->assertSuccessful();
    }

    public function test_non_manager_with_accounting_permissions_can_access_admin_financial_resources(): void
    {
        $accountant = User::factory()->create([
            'name' => 'Accountant User',
            'email' => 'accountant@example.com',
            'username' => 'accountant-user',
            'is_active' => true,
        ]);

        $accountantRole = Role::firstOrCreate(
            ['name' => 'accountant'],
            ['display_name' => 'Accountant'],
        );

        $accountantRole->givePermissionTo('expenses.viewAny');
        $accountant->roles()->sync([$accountantRole->id]);

        $this->actingAs($accountant)
            ->get('/admin/expenses')
            ->assertSuccessful();
    }

    public function test_system_permission_catalog_is_seeded_completely(): void
    {
        $this->assertSame(count(SystemPermissions::all()), Permission::count());
    }
}
