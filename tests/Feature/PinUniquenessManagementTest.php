<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinUniquenessManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
    }

    public function test_active_pin_conflict_helper_ignores_inactive_users_and_same_record(): void
    {
        $active = User::factory()->create([
            'pin' => '7777',
            'is_active' => true,
        ]);

        User::factory()->create([
            'pin' => '7777',
            'is_active' => false,
        ]);

        $this->assertTrue(User::activePinConflictExists('7777'));
        $this->assertFalse(User::activePinConflictExists('7777', $active->id));
        $this->assertFalse(User::activePinConflictExists('9999'));
    }

    public function test_cannot_activate_user_when_same_pin_is_used_by_another_active_user(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $adminRole = Role::firstWhere('name', 'admin') ?? Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
        ]);
        $admin->roles()->sync([$adminRole->id]);

        User::factory()->create([
            'pin' => '8888',
            'is_active' => true,
        ]);

        $inactive = User::factory()->create([
            'pin' => '8888',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertSuccessful();

        $inactive->refresh();
        $this->assertFalse($inactive->is_active);
        $this->assertTrue(User::activePinConflictExists('8888', $inactive->id));
    }
}
