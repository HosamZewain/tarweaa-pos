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

    public function test_admin_panel_login_accepts_username_for_manager(): void
    {
        $this->artisan('db:seed');

        $managerRole = Role::where('name', 'manager')->firstOrFail();

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
}
