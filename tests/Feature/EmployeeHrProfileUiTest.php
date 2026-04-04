<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeePenaltiesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeeSalariesRelationManager;
use App\Models\EmployeePenalty;
use App\Models\EmployeeSalary;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHrProfileUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_resource_exposes_salary_and_penalty_relation_managers(): void
    {
        $this->artisan('db:seed');

        $relations = EmployeeResource::getRelations();

        $this->assertContains(EmployeeSalariesRelationManager::class, $relations);
        $this->assertContains(EmployeePenaltiesRelationManager::class, $relations);
    }

    public function test_employee_edit_page_shows_hr_summary_and_relation_sections(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        $employee = User::factory()->create([
            'name' => 'Profile Employee',
            'username' => 'profile-employee',
            'is_active' => true,
        ]);
        $employee->roles()->sync([Role::firstWhere('name', 'cashier')->id]);
        $employee->employeeProfile()->create([
            'full_name' => 'Profile Employee Full',
            'job_title' => 'Cashier',
            'hired_at' => now()->toDateString(),
        ]);
        EmployeeSalary::query()->create([
            'user_id' => $employee->id,
            'amount' => 6500,
            'effective_from' => now()->startOfMonth()->toDateString(),
        ]);
        EmployeePenalty::query()->create([
            'user_id' => $employee->id,
            'penalty_date' => now()->toDateString(),
            'amount' => 80,
            'reason' => 'Late arrival',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get("/admin/employees/{$employee->id}/edit")
            ->assertSuccessful()
            ->assertSee('ملخص HR')
            ->assertSee('الراتب الحالي')
            ->assertSee('عدد الجزاءات النشطة')
            ->assertSee('الرواتب')
            ->assertSee('الجزاءات');
    }
}
