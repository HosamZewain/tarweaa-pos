<?php

namespace Tests\Feature;

use App\Filament\Pages\Payroll;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PayrollPageTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();
    }

    public function test_admin_can_access_payroll_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/payroll')
            ->assertSuccessful()
            ->assertSee('Payroll');
    }

    public function test_user_with_explicit_payroll_view_permission_can_access_payroll_page(): void
    {
        $user = $this->makeBackofficeUser([
            'hr.payroll.view',
        ]);

        $this->actingAs($user)
            ->get('/admin/payroll')
            ->assertSuccessful()
            ->assertSee('Payroll');
    }

    public function test_user_without_payroll_view_permission_cannot_access_payroll_page(): void
    {
        $user = $this->makeBackofficeUser([
            'employees.viewAny',
        ]);

        $this->actingAs($user)
            ->get('/admin/payroll')
            ->assertForbidden();
    }

    public function test_generate_and_approve_actions_follow_permissions(): void
    {
        $viewer = $this->makeBackofficeUser([
            'hr.payroll.view',
        ]);

        $generator = $this->makeBackofficeUser([
            'hr.payroll.view',
            'hr.payroll.generate',
        ]);

        Livewire::actingAs($viewer)
            ->test(Payroll::class)
            ->assertActionHidden('generatePayroll')
            ->assertActionHidden('approvePayroll');

        Livewire::actingAs($generator)
            ->test(Payroll::class)
            ->assertActionVisible('generatePayroll')
            ->assertActionHidden('approvePayroll');
    }

    private function makeBackofficeUser(array $permissionNames): User
    {
        $user = User::factory()->create([
            'name' => 'Payroll Access User',
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'payroll-access-' . fake()->unique()->slug(2),
            'display_name' => 'Payroll Access Role',
            'is_active' => true,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }
}
