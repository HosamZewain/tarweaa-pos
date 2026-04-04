<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeePenaltyResource\Pages\CreateEmployeePenalty;
use App\Filament\Resources\EmployeeSalaryResource\Pages\CreateEmployeeSalary;
use App\Models\EmployeePenalty;
use App\Models\EmployeeSalary;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HrResourceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $managerUser;
    protected User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->managerUser = User::factory()->create([
            'name' => 'HR Manager',
            'email' => 'hr.manager@example.com',
            'username' => 'hr-manager',
            'is_active' => true,
        ]);
        $this->managerUser->roles()->sync([Role::firstWhere('name', 'manager')->id]);

        $this->employeeUser = User::factory()->create([
            'name' => 'Operational Employee',
            'email' => 'operational.employee@example.com',
            'username' => 'operational-employee',
            'is_active' => true,
        ]);
        $this->employeeUser->roles()->sync([Role::firstWhere('name', 'cashier')->id]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_manager_can_create_employee_salary_record(): void
    {
        Livewire::actingAs($this->managerUser);

        Livewire::test(CreateEmployeeSalary::class)
            ->fillForm([
                'user_id' => $this->employeeUser->id,
                'amount' => 6500,
                'effective_from' => '2026-04-01',
                'effective_to' => '2026-06-30',
                'notes' => 'Quarterly salary baseline',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $salary = EmployeeSalary::query()->where('user_id', $this->employeeUser->id)->first();

        $this->assertNotNull($salary);
        $this->assertSame('6500.00', $salary->amount);
        $this->assertSame('2026-04-01', $salary->effective_from?->toDateString());
        $this->assertSame('2026-06-30', $salary->effective_to?->toDateString());
    }

    public function test_manager_can_create_employee_penalty_record(): void
    {
        Livewire::actingAs($this->managerUser);

        Livewire::test(CreateEmployeePenalty::class)
            ->fillForm([
                'user_id' => $this->employeeUser->id,
                'penalty_date' => '2026-04-04',
                'amount' => 150,
                'reason' => 'Late arrival',
                'is_active' => true,
                'notes' => 'Recorded after branch review',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $penalty = EmployeePenalty::query()->where('user_id', $this->employeeUser->id)->first();

        $this->assertNotNull($penalty);
        $this->assertSame('150.00', $penalty->amount);
        $this->assertSame('2026-04-04', $penalty->penalty_date?->toDateString());
        $this->assertSame('Late arrival', $penalty->reason);
        $this->assertTrue($penalty->is_active);
    }
}
