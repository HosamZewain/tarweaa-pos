<?php

namespace Tests\Feature;

use App\Models\EmployeeAdvance;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAdvancesReportTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $managerUser;
    private Role $managerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();
        $this->managerUser = User::factory()->create([
            'name' => 'Advances Manager',
            'email' => 'advances.manager@example.com',
            'username' => 'advances-manager',
            'is_active' => true,
        ]);
        $this->managerRole = Role::firstWhere('name', 'manager');
        $this->managerUser->roles()->sync([$this->managerRole->id]);
    }

    public function test_report_service_returns_advances_summary_and_grouping_by_employee(): void
    {
        $firstEmployee = $this->makeEmployee('first-advance-employee', 'Cashier One');
        $secondEmployee = $this->makeEmployee('second-advance-employee', 'Cashier Two');

        EmployeeAdvance::query()->create([
            'user_id' => $firstEmployee->id,
            'amount' => 500,
            'advance_date' => '2026-04-02',
            'status' => 'active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);
        EmployeeAdvance::query()->create([
            'user_id' => $firstEmployee->id,
            'amount' => 200,
            'advance_date' => '2026-04-03',
            'status' => 'cancelled',
            'cancelled_by' => $this->adminUser->id,
            'cancelled_at' => now(),
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);
        EmployeeAdvance::query()->create([
            'user_id' => $secondEmployee->id,
            'amount' => 300,
            'advance_date' => '2026-04-04',
            'status' => 'active',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $report = app(ReportService::class)->getEmployeeAdvancesReport('2026-04-01', '2026-04-30');

        $this->assertSame(3, $report['totals']['total_advances']);
        $this->assertSame(2, $report['totals']['active_advances']);
        $this->assertSame(1, $report['totals']['cancelled_advances']);
        $this->assertSame(1000.0, $report['totals']['total_amount']);
        $this->assertSame(800.0, $report['totals']['active_amount']);
        $this->assertSame(200.0, $report['totals']['cancelled_amount']);

        $firstEmployeeSummary = $report['byEmployee']->firstWhere('employee_id', $firstEmployee->id);

        $this->assertSame(2, $firstEmployeeSummary['total_advances']);
        $this->assertSame(700.0, $firstEmployeeSummary['total_amount']);
        $this->assertSame(500.0, $firstEmployeeSummary['active_amount']);
        $this->assertSame(200.0, $firstEmployeeSummary['cancelled_amount']);
    }

    public function test_admin_can_view_employee_advances_report_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/employee-advances-report')
            ->assertSuccessful()
            ->assertSee('تقرير سلف الموظفين');
    }

    public function test_user_without_permission_cannot_access_employee_advances_report_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Advances Report User',
            'username' => 'advances-report-user',
            'is_active' => true,
        ]);
        $role = Role::create([
            'name' => 'advances-report-auditor',
            'display_name' => 'Advances Report Auditor',
        ]);
        $role->givePermissionTo(['employees.viewAny']);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get('/admin/employee-advances-report')
            ->assertForbidden();

        $this->actingAs($this->managerUser->fresh())
            ->get('/admin/employee-advances-report')
            ->assertSuccessful();
    }

    private function makeEmployee(string $username, string $name): User
    {
        $employee = User::factory()->create([
            'name' => $name,
            'username' => $username,
            'is_active' => true,
        ]);
        $employee->roles()->sync([Role::firstWhere('name', 'cashier')->id]);
        $employee->employeeProfile()->create([
            'full_name' => $name . ' Full',
            'job_title' => 'Cashier',
            'hired_at' => now()->toDateString(),
        ]);

        return $employee;
    }
}
