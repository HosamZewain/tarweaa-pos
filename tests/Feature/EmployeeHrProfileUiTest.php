<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeeAdvancesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeePenaltiesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeeSalariesRelationManager;
use App\Models\EmployeeAdvance;
use App\Models\EmployeePenalty;
use App\Models\EmployeeSalary;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHrProfileUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_resource_exposes_advance_salary_and_penalty_relation_managers(): void
    {
        $this->artisan('db:seed');

        $relations = EmployeeResource::getRelations();

        $this->assertContains(EmployeeAdvancesRelationManager::class, $relations);
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
        EmployeeAdvance::query()->create([
            'user_id' => $employee->id,
            'amount' => 500,
            'advance_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        EmployeePenalty::query()->create([
            'user_id' => $employee->id,
            'penalty_date' => now()->toDateString(),
            'amount' => 80,
            'reason' => 'Late arrival',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get("/admin/employees/{$employee->id}")
            ->assertSuccessful()
            ->assertSee('ملخص HR')
            ->assertSee('الراتب الحالي')
            ->assertSee('عدد الجزاءات النشطة')
            ->assertSee('عدد السلف النشطة')
            ->assertSee('إجمالي السلف النشطة')
            ->assertSee('السلف')
            ->assertSee('الرواتب')
            ->assertSee('الجزاءات')
            ->assertSee('إضافة سلفة')
            ->assertSee('إضافة راتب')
            ->assertSee('إضافة جزاء');
    }

    public function test_employee_edit_page_keeps_sensitive_editing_separate_from_hr_relation_sections(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        $employee = User::factory()->create([
            'name' => 'Edit Separation Employee',
            'username' => 'edit-separation-employee',
            'is_active' => true,
        ]);
        $employee->roles()->sync([Role::firstWhere('name', 'cashier')->id]);

        $response = $this->actingAs($admin)
            ->get("/admin/employees/{$employee->id}/edit");

        $response->assertSuccessful();
        $response->assertDontSee('إضافة سلفة');
        $response->assertDontSee('إضافة راتب');
        $response->assertDontSee('إضافة جزاء');
    }
}
