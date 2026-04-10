<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalLauncherEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
    }

    public function test_launcher_shows_user_summary_blocks(): void
    {
        $user = User::factory()->create([
            'name' => 'Portal User',
            'username' => 'portal-user',
            'email' => 'portal@example.com',
            'phone' => '01000000000',
            'is_active' => true,
            'last_login_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        $role->givePermissionTo('dashboard.view');
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get('/launcher')
            ->assertSuccessful()
            ->assertSee('بيانات الحساب')
            ->assertSee('portal-user')
            ->assertSee('portal@example.com')
            ->assertSee('01000000000')
            ->assertSee('إجراءات سريعة')
            ->assertSee('تغيير كلمة المرور');
    }

    public function test_authenticated_user_can_change_password_from_portal(): void
    {
        $user = User::factory()->create([
            'username' => 'password-user',
            'password' => 'old-password',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'cashier'], ['display_name' => 'Cashier']);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->postJson('/portal/password', [
                'current_password' => 'old-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث كلمة المرور بنجاح.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'cashier'], ['display_name' => 'Cashier']);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->postJson('/portal/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'كلمة المرور الحالية غير صحيحة.');
    }
}
