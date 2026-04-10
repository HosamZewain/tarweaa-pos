<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EmployeeResource\Pages\EditEmployee;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeResourceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;
    protected User $managerUser;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->ownerUser = User::factory()->create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'username' => 'owner-user',
            'is_active' => true,
        ]);

        $this->managerUser = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'username' => 'manager-user',
            'is_active' => true,
        ]);

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();

        $ownerRole = Role::firstWhere('name', 'owner');
        $ownerRole->givePermissionTo([
            'dashboard.view',
            'employees.viewAny',
        ]);
        $this->ownerUser->roles()->sync([$ownerRole->id]);

        $managerRole = Role::firstWhere('name', 'manager');
        $managerRole->givePermissionTo([
            'employees.viewAny',
            'employees.view',
            'employees.create',
            'employees.update',
        ]);
        $this->managerUser->roles()->sync([$managerRole->id]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_owner_with_explicit_permissions_can_access_employee_resource_but_not_roles_or_permissions_modules(): void
    {
        $this->actingAs($this->ownerUser)
            ->get('/admin')
            ->assertSuccessful();

        $this->actingAs($this->ownerUser)
            ->get('/admin/employees')
            ->assertSuccessful()
            ->assertSee('الموظفون');

        $this->actingAs($this->ownerUser)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($this->ownerUser)
            ->get('/admin/roles')
            ->assertForbidden();

        $this->actingAs($this->ownerUser)
            ->get('/admin/permissions')
            ->assertForbidden();
    }

    public function test_admin_can_still_access_full_users_module(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/users')
            ->assertSuccessful()
            ->assertSee('المستخدمون');
    }

    public function test_manager_can_create_cashier_from_employee_resource(): void
    {
        Livewire::actingAs($this->managerUser);
        Livewire::test(CreateEmployee::class)
            ->fillForm([
                'name' => 'Cashier Employee',
                'username' => 'cashier-employee',
                'email' => 'cashier@example.com',
                'phone' => '01000000001',
                'password' => 'secret123',
                'pin' => '4567',
                'is_active' => true,
                'employeeProfile.full_name' => 'Cashier Employee Full Name',
                'employeeProfile.job_title' => 'Senior Cashier',
                'employeeProfile.hired_at' => '2026-03-01',
                'employeeProfile.notes' => 'Operational note',
                'staff_role' => 'cashier',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $createdUser = User::where('username', 'cashier-employee')->first();

        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole('cashier'));
        $this->assertFalse($createdUser->hasRole(['admin', 'manager', 'owner']));
        $this->assertNotNull($createdUser->employeeProfile);
        $this->assertSame('Cashier Employee Full Name', $createdUser->employeeProfile->full_name);
        $this->assertSame('Senior Cashier', $createdUser->employeeProfile->job_title);
        $this->assertSame('2026-03-01', $createdUser->employeeProfile->hired_at?->toDateString());
    }

    public function test_employee_resource_excludes_admin_accounts_from_management_routes(): void
    {
        $this->actingAs($this->managerUser)
            ->get("/admin/employees/{$this->adminUser->id}/edit")
            ->assertNotFound();
    }

    public function test_operational_employees_and_back_office_users_do_not_overlap_between_users_and_employees_resources(): void
    {
        $cashierEmployee = User::factory()->create([
            'name' => 'Cashier Employee',
            'username' => 'cashier-employee',
            'is_active' => true,
        ]);
        $cashierEmployee->roles()->sync([Role::firstWhere('name', 'cashier')->id]);

        $accountantRole = Role::firstOrCreate(
            ['name' => 'accountant'],
            ['display_name' => 'Accountant'],
        );
        $accountantRole->givePermissionTo([
            'expenses.viewAny',
            'expense_categories.viewAny',
            'reports.expenses.view',
        ]);

        $accountantUser = User::factory()->create([
            'name' => 'Accountant User',
            'username' => 'accountant-user',
            'is_active' => true,
        ]);
        $accountantUser->roles()->sync([$accountantRole->id]);

        $this->actingAs($this->adminUser)
            ->get('/admin/users')
            ->assertSuccessful()
            ->assertSee('accountant-user')
            ->assertDontSee('cashier-employee');

        $this->actingAs($this->adminUser)
            ->get('/admin/employees')
            ->assertSuccessful()
            ->assertSee('cashier-employee')
            ->assertDontSee('accountant-user');
    }

    public function test_role_flag_controls_whether_role_appears_in_employee_resource(): void
    {
        $deliveryRole = Role::create([
            'name' => 'delivery_runner',
            'display_name' => 'Delivery Runner',
            'is_active' => true,
            'show_in_employee_resource' => true,
        ]);

        $backOfficeRole = Role::create([
            'name' => 'auditor',
            'display_name' => 'Auditor',
            'is_active' => true,
            'show_in_employee_resource' => false,
        ]);

        $deliveryUser = User::factory()->create([
            'name' => 'Delivery User',
            'username' => 'delivery-user',
            'is_active' => true,
        ]);
        $deliveryUser->roles()->sync([$deliveryRole->id]);

        $auditorUser = User::factory()->create([
            'name' => 'Auditor User',
            'username' => 'auditor-user',
            'is_active' => true,
        ]);
        $auditorUser->roles()->sync([$backOfficeRole->id]);

        $this->actingAs($this->adminUser)
            ->get('/admin/employees')
            ->assertSuccessful()
            ->assertSee('delivery-user')
            ->assertDontSee('auditor-user');

        $this->actingAs($this->adminUser)
            ->get('/admin/users')
            ->assertSuccessful()
            ->assertSee('auditor-user')
            ->assertDontSee('delivery-user');
    }

    public function test_manager_can_update_employee_profile_details(): void
    {
        $employee = User::factory()->create([
            'name' => 'Kitchen User',
            'username' => 'kitchen-user',
            'is_active' => true,
        ]);
        $employee->roles()->sync([Role::firstWhere('name', 'kitchen')->id]);

        Livewire::actingAs($this->managerUser);

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->fillForm([
                'name' => 'Kitchen User Updated',
                'employeeProfile.full_name' => 'Kitchen Employee Full',
                'employeeProfile.job_title' => 'Kitchen Supervisor',
                'employeeProfile.hired_at' => '2026-02-15',
                'employeeProfile.notes' => 'Updated notes',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $employee->refresh();

        $this->assertSame('Kitchen User Updated', $employee->name);
        $this->assertNotNull($employee->employeeProfile);
        $this->assertSame('Kitchen Employee Full', $employee->employeeProfile->full_name);
        $this->assertSame('Kitchen Supervisor', $employee->employeeProfile->job_title);
        $this->assertSame('2026-02-15', $employee->employeeProfile->hired_at?->toDateString());
        $this->assertSame($this->managerUser->id, $employee->employeeProfile->updated_by);
    }

    public function test_employee_profile_can_store_attachments_metadata(): void
    {
        $employee = User::factory()->create([
            'name' => 'Attachment User',
            'username' => 'attachment-user',
            'is_active' => true,
        ]);

        $profile = $employee->employeeProfile()->create([
            'full_name' => 'Attachment User Full',
            'job_title' => 'Staff Member',
            'created_by' => $this->managerUser->id,
            'updated_by' => $this->managerUser->id,
        ]);

        $attachment = $profile->attachments()->create([
            'title' => 'Employment Contract',
            'file_path' => 'employees/attachments/contract.pdf',
            'file_name' => 'contract.pdf',
            'file_type' => 'pdf',
            'uploaded_by' => $this->managerUser->id,
        ]);

        $this->assertSame('Employment Contract', $attachment->title);
        $this->assertSame('employees/attachments/contract.pdf', $attachment->file_path);
        $this->assertSame($this->managerUser->id, $attachment->uploaded_by);
    }
}
