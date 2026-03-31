<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');
    }

    public function test_password_login_creates_shared_web_session_and_redirects_single_surface_user(): void
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier User',
            'username' => 'cashier-user',
            'email' => 'cashier@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(['name' => 'cashier'], ['display_name' => 'Cashier']);
        $cashier->roles()->sync([$cashierRole->id]);

        $response = $this->postJson('/portal/login', [
            'username' => 'cashier-user',
            'password' => 'secret123',
            'device_name' => 'portal-web',
        ])->assertOk();

        $response->assertJsonPath('data.redirect_url', '/pos/drawer');

        $this->assertAuthenticatedAs($cashier);
        $this->get('/pos/drawer')->assertSuccessful();
    }

    public function test_entry_page_shows_save_login_info_option(): void
    {
        $this->get('/')
            ->assertSuccessful()
            ->assertSee('حفظ بيانات الدخول');
    }

    public function test_launcher_redirects_single_surface_users_directly_to_their_only_surface(): void
    {
        $user = User::factory()->create([
            'name' => 'Kitchen User',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'kitchen'], ['display_name' => 'Kitchen']);
        $permission = Permission::firstOrCreate(['name' => 'view_kitchen'], ['display_name' => 'View Kitchen']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get('/launcher')
            ->assertRedirect('/kitchen');
    }

    public function test_launcher_lists_multiple_allowed_surfaces_and_admin_card_when_applicable(): void
    {
        $manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'username' => 'manager-user',
            'is_active' => true,
        ]);

        $managerRole = Role::firstWhere('name', 'manager') ?? Role::create([
            'name' => 'manager',
            'display_name' => 'Manager',
        ]);
        $permissionIds = collect(['view_kitchen', 'view_counter_screen'])->map(
            fn (string $name) => Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name],
            )->id,
        );
        $managerRole->permissions()->syncWithoutDetaching($permissionIds);
        $manager->roles()->sync([$managerRole->id]);

        $this->actingAs($manager)
            ->get('/launcher')
            ->assertSuccessful()
            ->assertSee('نقطة البيع')
            ->assertSee('المطبخ')
            ->assertSee('الكاونتر')
            ->assertSee('لوحة الإدارة');

        $this->actingAs($manager)
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_surface_middleware_redirects_authenticated_users_to_their_home_when_forbidden(): void
    {
        $user = User::factory()->create([
            'name' => 'Kitchen Only',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'kitchen'], ['display_name' => 'Kitchen']);
        $permission = Permission::firstOrCreate(['name' => 'view_kitchen'], ['display_name' => 'View Kitchen']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get('/counter')
            ->assertRedirect('/kitchen');
    }
}
