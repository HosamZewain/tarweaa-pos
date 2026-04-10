<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_does_not_reset_existing_admin_credentials(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();
        $admin->update([
            'username' => 'custom-admin',
            'password' => Hash::make('custom-secret'),
            'pin' => '9876',
            'phone' => '01111111111',
        ]);

        $this->artisan('db:seed');

        $admin->refresh();

        $this->assertSame('custom-admin', $admin->username);
        $this->assertSame('9876', $admin->pin);
        $this->assertSame('01111111111', $admin->phone);
        $this->assertTrue(Hash::check('custom-secret', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    public function test_reseeding_assigns_all_permissions_to_admin_role_only(): void
    {
        $this->artisan('db:seed');

        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->assertSame(Permission::count(), $adminRole->permissions()->count());

        foreach (['owner', 'manager', 'cashier', 'kitchen', 'counter', 'employee'] as $roleName) {
            $role = Role::where('name', $roleName)->firstOrFail();

            $this->assertSame(0, $role->permissions()->count(), "Role [{$roleName}] should not keep default permissions.");
        }
    }

    public function test_reseeding_preserves_existing_custom_role_permissions(): void
    {
        $this->artisan('db:seed');

        $managerRole = Role::where('name', 'manager')->firstOrFail();
        $permission = Permission::where('name', 'reports.sales.view')->firstOrFail();

        $managerRole->permissions()->sync([$permission->id]);

        $this->artisan('db:seed');

        $managerRole->refresh();

        $this->assertTrue(
            $managerRole->permissions()->where('permissions.id', $permission->id)->exists(),
            'Custom manager permissions should survive reseeding.'
        );
    }

    public function test_admin_role_permissions_are_controlled_by_role_permissions_table(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->assertTrue($admin->hasPermission('employees.viewAny'));

        $adminRole->revokePermissionTo('employees.viewAny');
        $admin->refresh();
        $admin->forgetAuthorizationCache();

        $this->assertFalse($admin->hasPermission('employees.viewAny'));
    }
}
