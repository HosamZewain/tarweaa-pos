<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_without_email(): void
    {
        $this->artisan('migrate');
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'كاشير']
        );

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Ramy',
                'username' => 'ramy',
                'email' => null,
                'phone' => null,
                'password' => 'password123',
                'pin' => '9876',
                'is_active' => true,
                'roles' => [$cashierRole->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'username' => 'ramy',
            'email' => null,
            'is_active' => true,
        ]);
    }
}
