<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeAdvanceResource;
use App\Filament\Resources\EmployeeAdvanceResource\Pages\CreateEmployeeAdvance;
use App\Filament\Resources\EmployeeAdvanceResource\Pages\EditEmployeeAdvance;
use App\Models\AdminActivityLog;
use App\Models\EmployeeAdvance;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeAdvanceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $managerUser;
    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->managerUser = User::factory()->create([
            'name' => 'Advance Manager',
            'email' => 'advance.manager@example.com',
            'username' => 'advance-manager',
            'is_active' => true,
        ]);
        $managerRole = Role::firstWhere('name', 'manager');
        $managerRole->givePermissionTo([
            'employee_advances.viewAny',
            'employee_advances.view',
            'employee_advances.create',
            'employee_advances.update',
            'employee_advances.cancel',
        ]);
        $this->managerUser->roles()->sync([$managerRole->id]);

        $this->employeeUser = User::factory()->create([
            'name' => 'Advance Employee',
            'email' => 'advance.employee@example.com',
            'username' => 'advance-employee',
            'is_active' => true,
        ]);
        $this->employeeUser->roles()->sync([Role::firstWhere('name', 'cashier')->id]);
    }

    public function test_manager_can_create_employee_advance_record(): void
    {
        Livewire::actingAs($this->managerUser)
            ->test(CreateEmployeeAdvance::class)
            ->fillForm([
                'user_id' => $this->employeeUser->id,
                'amount' => 850,
                'advance_date' => '2026-04-07',
                'notes' => 'Emergency staff advance',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $advance = EmployeeAdvance::query()->where('user_id', $this->employeeUser->id)->first();

        $this->assertNotNull($advance);
        $this->assertSame('850.00', $advance->amount);
        $this->assertSame('2026-04-07', $advance->advance_date?->toDateString());
        $this->assertSame('active', $advance->status);
        $this->assertSame($this->managerUser->id, $advance->created_by);
    }

    public function test_manager_can_cancel_employee_advance_from_edit_page(): void
    {
        $advance = EmployeeAdvance::query()->create([
            'user_id' => $this->employeeUser->id,
            'amount' => 400,
            'advance_date' => '2026-04-05',
            'status' => 'active',
            'notes' => 'Short-term advance',
            'created_by' => $this->managerUser->id,
            'updated_by' => $this->managerUser->id,
        ]);

        Livewire::actingAs($this->managerUser)
            ->test(EditEmployeeAdvance::class, ['record' => $advance->getRouteKey()])
            ->assertActionVisible('cancelAdvance')
            ->callAction('cancelAdvance', data: [
                'reason' => 'Entered by mistake',
            ]);

        $advance->refresh();

        $this->assertSame('cancelled', $advance->status);
        $this->assertSame($this->managerUser->id, $advance->cancelled_by);
        $this->assertSame('Entered by mistake', $advance->cancellation_reason);
        $this->assertNotNull($advance->cancelled_at);

        $activity = AdminActivityLog::query()
            ->where('action', 'cancelled')
            ->where('subject_type', $advance->getMorphClass())
            ->where('subject_id', $advance->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->managerUser->id, $activity->actor_user_id);
        $this->assertSame('active', $activity->old_values['status']);
        $this->assertSame('cancelled', $activity->new_values['status']);
    }

    public function test_cancelled_advance_cannot_be_edited_even_with_update_permission(): void
    {
        $advance = EmployeeAdvance::query()->create([
            'user_id' => $this->employeeUser->id,
            'amount' => 275,
            'advance_date' => '2026-04-06',
            'status' => 'cancelled',
            'cancelled_by' => $this->managerUser->id,
            'cancelled_at' => now(),
            'created_by' => $this->managerUser->id,
            'updated_by' => $this->managerUser->id,
        ]);

        $this->actingAs($this->managerUser)
            ->get(EmployeeAdvanceResource::getUrl('edit', ['record' => $advance]))
            ->assertForbidden();
    }
}
