<?php

namespace Tests\Feature;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvancePayrollAllocation;
use App\Models\EmployeePenalty;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();
    }

    public function test_preview_month_calculates_salary_penalties_and_advances(): void
    {
        $employee = $this->makeEmployee('payroll-preview-user', 'Payroll Preview Employee');

        EmployeeSalary::query()->create([
            'user_id' => $employee->id,
            'amount' => 2000,
            'effective_from' => '2026-04-01',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        EmployeePenalty::query()->create([
            'user_id' => $employee->id,
            'penalty_date' => '2026-04-12',
            'amount' => 150,
            'reason' => 'Late attendance',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        EmployeePenalty::query()->create([
            'user_id' => $employee->id,
            'penalty_date' => '2026-03-29',
            'amount' => 90,
            'reason' => 'Previous month penalty',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        EmployeeAdvance::query()->create([
            'user_id' => $employee->id,
            'amount' => 300,
            'advance_date' => '2026-04-08',
            'status' => 'active',
            'notes' => 'Travel advance',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $payload = app(PayrollService::class)->previewMonth('2026-04');
        $line = collect($payload['lines'])->firstWhere('employee_name', 'Payroll Preview Employee Full');

        $this->assertNotNull($line);
        $this->assertSame(2000.0, $line['base_salary']);
        $this->assertSame(150.0, $line['penalties_total']);
        $this->assertSame(300.0, $line['advances_total']);
        $this->assertSame(1550.0, $line['net_salary']);
        $this->assertSame(1, $line['penalties_count']);
        $this->assertSame(1, $line['advances_count']);
        $this->assertCount(1, $payload['penalties']);
        $this->assertCount(1, $payload['advances']);
    }

    public function test_generated_run_keeps_penalty_snapshot_and_does_not_rededuct_approved_advance(): void
    {
        $this->actingAs($this->adminUser);

        $employee = $this->makeEmployee('payroll-snapshot-user', 'Payroll Snapshot Employee');

        EmployeeSalary::query()->create([
            'user_id' => $employee->id,
            'amount' => 1000,
            'effective_from' => '2026-04-01',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $penalty = EmployeePenalty::query()->create([
            'user_id' => $employee->id,
            'penalty_date' => '2026-04-18',
            'amount' => 100,
            'reason' => 'Damaged tools',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $advance = EmployeeAdvance::query()->create([
            'user_id' => $employee->id,
            'amount' => 250,
            'advance_date' => '2026-04-09',
            'status' => 'active',
            'notes' => 'Emergency cash',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $run = app(PayrollService::class)->generateMonth('2026-04', $this->adminUser);

        $this->assertInstanceOf(PayrollRun::class, $run);
        $this->assertSame('draft', $run->status);
        $this->assertSame(1, $run->lines()->count());
        $this->assertSame(1, EmployeeAdvancePayrollAllocation::query()->count());

        $line = $run->lines()->firstOrFail();
        $this->assertSame(100.0, (float) $line->penalties_total);
        $this->assertSame(250.0, (float) $line->advances_total);
        $this->assertSame(650.0, (float) $line->net_salary);
        $this->assertCount(1, $line->penalties_snapshot ?? []);
        $this->assertCount(1, $line->advances_snapshot ?? []);

        $penalty->update([
            'is_active' => false,
            'reason' => 'Edited after payroll generation',
            'updated_by' => $this->adminUser->id,
        ]);

        $savedPayload = app(PayrollService::class)->payloadForRun($run->fresh(['lines.advanceAllocations.advance.employee.employeeProfile']));
        $savedPenalty = $savedPayload['penalties'][0] ?? null;

        $this->assertNotNull($savedPenalty);
        $this->assertSame('Damaged tools', $savedPenalty['reason']);
        $this->assertSame(100.0, $savedPenalty['amount']);

        $approvedRun = app(PayrollService::class)->approve($run->fresh(), $this->adminUser);
        $this->assertSame('approved', $approvedRun->status);

        $mayPayload = app(PayrollService::class)->previewMonth('2026-05');
        $mayLine = collect($mayPayload['lines'])->firstWhere('employee_name', 'Payroll Snapshot Employee Full');

        $this->assertNotNull($mayLine);
        $this->assertSame(0.0, $mayLine['penalties_total']);
        $this->assertSame(0.0, $mayLine['advances_total']);
        $this->assertSame(1000.0, $mayLine['net_salary']);

        $this->assertSame($advance->id, $line->advanceAllocations()->firstOrFail()->employee_advance_id);
    }

    private function makeEmployee(string $username, string $name): User
    {
        $employee = User::factory()->create([
            'name' => $name,
            'username' => $username,
            'email' => "{$username}@example.com",
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
