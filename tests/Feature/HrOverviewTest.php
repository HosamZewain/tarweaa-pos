<?php

namespace Tests\Feature;

use App\Models\EmployeePenalty;
use App\Models\EmployeeSalary;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_overview_report_summarizes_employees_salaries_penalties_and_benefits(): void
    {
        $this->artisan('db:seed');

        $cashierRole = Role::firstWhere('name', 'cashier');
        $kitchenRole = Role::firstWhere('name', 'kitchen');

        $firstEmployee = User::factory()->create([
            'name' => 'Cashier One',
            'username' => 'cashier-one',
            'is_active' => true,
        ]);
        $firstEmployee->roles()->sync([$cashierRole->id]);
        $firstEmployee->employeeProfile()->create([
            'full_name' => 'Cashier One Full',
            'job_title' => 'Cashier',
            'hired_at' => now()->startOfMonth()->addDay(),
        ]);
        EmployeeSalary::query()->create([
            'user_id' => $firstEmployee->id,
            'amount' => 5000,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => null,
        ]);
        EmployeePenalty::query()->create([
            'user_id' => $firstEmployee->id,
            'penalty_date' => now()->toDateString(),
            'amount' => 150,
            'reason' => 'Late arrival',
            'is_active' => true,
        ]);
        UserMealBenefitProfile::query()->create([
            'user_id' => $firstEmployee->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 300,
        ]);

        $secondEmployee = User::factory()->create([
            'name' => 'Kitchen Two',
            'username' => 'kitchen-two',
            'is_active' => false,
        ]);
        $secondEmployee->roles()->sync([$kitchenRole->id]);
        $secondEmployee->employeeProfile()->create([
            'full_name' => 'Kitchen Two Full',
            'job_title' => 'Kitchen',
            'hired_at' => now()->copy()->subMonth()->startOfMonth(),
        ]);
        EmployeeSalary::query()->create([
            'user_id' => $secondEmployee->id,
            'amount' => 4200,
            'effective_from' => now()->copy()->subMonths(3)->startOfMonth()->toDateString(),
            'effective_to' => now()->copy()->subMonth()->endOfMonth()->toDateString(),
        ]);
        EmployeePenalty::query()->create([
            'user_id' => $secondEmployee->id,
            'penalty_date' => now()->copy()->subDays(3)->toDateString(),
            'amount' => 75,
            'reason' => 'Uniform issue',
            'is_active' => false,
        ]);

        $report = app(ReportService::class)->getHrOverview();

        $this->assertSame(2, $report['summary']['total_employees']);
        $this->assertSame(1, $report['summary']['active_employees']);
        $this->assertSame(1, $report['summary']['inactive_employees']);
        $this->assertSame(2, $report['summary']['employees_with_profiles']);
        $this->assertSame(1, $report['summary']['hired_this_month']);
        $this->assertSame(1, $report['summary']['employees_with_current_salary']);
        $this->assertSame(1, $report['summary']['employees_without_current_salary']);
        $this->assertSame(5000.0, $report['summary']['total_current_salaries']);
        $this->assertSame(5000.0, $report['summary']['average_current_salary']);
        $this->assertSame(1, $report['summary']['active_penalties_count']);
        $this->assertSame(150.0, $report['summary']['active_penalties_total']);
        $this->assertSame(1, $report['summary']['active_benefit_profiles']);
        $this->assertSame(1, $report['summary']['allowance_profiles']);
        $this->assertSame(0, $report['summary']['owner_charge_profiles']);
        $this->assertSame(0, $report['summary']['free_meal_profiles']);

        $this->assertSame('Cashier One Full', $report['current_salaries'][0]['employee_name']);
        $this->assertSame(5000.0, $report['current_salaries'][0]['amount']);
        $this->assertSame('Late arrival', $report['recent_penalties'][0]['reason']);
        $this->assertSame(150.0, $report['recent_penalties'][0]['amount']);
        $this->assertSame('Cashier', collect($report['role_breakdown'])->firstWhere('role_label', 'Cashier')['role_label']);
    }
}
