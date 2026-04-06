<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_role_permissions_from_filament(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();
        $role = Role::firstWhere('name', 'manager');

        $permissionsByGroup = Permission::query()
            ->orderBy('group')
            ->orderBy('display_name')
            ->get()
            ->groupBy(fn (Permission $permission) => $permission->group ?: 'عام');

        $firstGroup = $permissionsByGroup->keys()->first();
        $firstPermissionId = (string) $permissionsByGroup->first()->first()->id;

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm([
                'name' => $role->name,
                'display_name' => 'Manager Updated',
                'description' => 'Updated role permission set',
                'is_active' => true,
                'show_in_employee_resource' => true,
                'permission_groups.' . \App\Filament\Resources\RoleResource::getPermissionGroupStateKey($firstGroup) => [$firstPermissionId],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $role->refresh();

        $this->assertSame('Manager Updated', $role->display_name);
        $this->assertTrue($role->show_in_employee_resource);
        $this->assertTrue($role->permissions()->whereKey((int) $firstPermissionId)->exists());
    }
}
