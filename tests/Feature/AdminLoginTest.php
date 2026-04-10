<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_login_accepts_username_for_manager_with_admin_permission(): void
    {
        $this->artisan('db:seed');

        $managerRole = Role::where('name', 'manager')->firstOrFail();
        $managerRole->givePermissionTo('dashboard.view');

        $manager = User::factory()->create([
            'name' => 'Manager User',
            'username' => 'manager.user',
            'email' => 'manager@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $manager->roles()->attach($managerRole->id);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'manager.user',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($manager);
    }

    public function test_admin_panel_login_rejects_non_admin_roles_with_clear_message(): void
    {
        $this->artisan('db:seed');

        $partnerRole = Role::firstOrCreate(
            ['name' => 'partner'],
            ['display_name' => 'Partner']
        );

        $partner = User::factory()->create([
            'name' => 'Partner User',
            'username' => 'partner.user',
            'email' => 'partner@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $partner->roles()->attach($partnerRole->id);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'partner@example.com',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_admin_panel_login_accepts_active_user_with_accounting_permissions(): void
    {
        $this->artisan('db:seed');

        $accountantRole = Role::firstOrCreate(
            ['name' => 'accountant'],
            ['display_name' => 'Accountant']
        );

        $accountantRole->givePermissionTo([
            'expenses.viewAny',
            'expense_categories.viewAny',
            'reports.expenses.view',
        ]);

        $accountant = User::factory()->create([
            'name' => 'Accountant User',
            'username' => 'accountant.user',
            'email' => 'accountant@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $accountant->roles()->attach($accountantRole->id);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'accountant.user',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($accountant);
    }

    public function test_admin_panel_login_rejects_operational_only_user_without_admin_permissions(): void
    {
        $this->artisan('db:seed');

        $kitchenRole = Role::firstOrCreate(
            ['name' => 'kitchen'],
            ['display_name' => 'Kitchen']
        );

        $kitchenRole->givePermissionTo([
            'view_kitchen',
            'mark_order_ready',
        ]);

        $kitchenUser = User::factory()->create([
            'name' => 'Kitchen User',
            'username' => 'kitchen.user',
            'email' => 'kitchen@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $kitchenUser->roles()->attach($kitchenRole->id);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'kitchen.user',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }
}
