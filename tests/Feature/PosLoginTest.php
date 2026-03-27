<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
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

    public function test_pin_login_rejects_duplicate_active_pins(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier']
        );

        User::factory()->create([
            'name' => 'Cashier One',
            'username' => 'cashier1',
            'pin' => '5555',
            'is_active' => true,
        ])->roles()->attach($role->id);

        User::factory()->create([
            'name' => 'Cashier Two',
            'username' => 'cashier2',
            'pin' => '5555',
            'is_active' => true,
        ])->roles()->attach($role->id);

        $this->postJson('/api/auth/pin-login', [
            'pin' => '5555',
            'device_name' => 'test-terminal',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'رمز PIN مستخدم لأكثر من حساب. يرجى استخدام كلمة المرور أو تعيين PIN مختلف لكل مستخدم.');
    }

    public function test_admin_pin_login_reports_kitchen_access(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin User',
            'username' => 'admin',
            'pin' => '1234',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator']
        );

        $kitchenPermission = Permission::firstOrCreate(
            ['name' => 'view_kitchen'],
            ['display_name' => 'عرض شاشة المطبخ', 'group' => 'المطبخ']
        );

        $role->permissions()->syncWithoutDetaching([$kitchenPermission->id]);
        $user->roles()->attach($role->id);

        $this->postJson('/api/auth/pin-login', [
            'pin' => '1234',
            'device_name' => 'test-terminal',
        ])->assertOk()
            ->assertJsonPath('data.user.can_access_kitchen', true);
    }
}
