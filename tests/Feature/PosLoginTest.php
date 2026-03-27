<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_password_login_accepts_email_identifier(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator']
        );

        $user->roles()->attach($role->id);

        $this->postJson('/api/auth/login', [
            'username' => 'admin@example.com',
            'password' => 'secret123',
            'device_name' => 'test-terminal',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.user.can_access_pos', true);
    }
}
