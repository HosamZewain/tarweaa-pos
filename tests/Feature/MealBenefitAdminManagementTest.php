<?php

namespace Tests\Feature;

use App\Enums\MealBenefitLedgerEntryType;
use App\Filament\Resources\UserMealBenefitProfileResource\Pages\CreateUserMealBenefitProfile;
use App\Filament\Resources\UserMealBenefitProfileResource\Pages\EditUserMealBenefitProfile;
use App\Filament\Resources\UserMealBenefitProfileResource\Pages\ListUserMealBenefitProfiles;
use App\Models\MealBenefitLedgerEntry;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMealBenefitProfile;
use App\Services\MealBenefitLedgerService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MealBenefitAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->first()
            ?? User::factory()->create([
                'email' => 'admin@pos.com',
                'is_active' => true,
            ]);

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator', 'is_active' => true],
        );

        if (!$this->adminUser->roles->contains($adminRole->id)) {
            $this->adminUser->roles()->attach($adminRole->id);
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_configure_owner_charge_profile(): void
    {
        $owner = User::factory()->create([
            'name' => 'Owner Account',
            'username' => 'owner-account',
            'is_active' => true,
        ]);
        $ownerRole = Role::firstOrCreate(['name' => 'owner'], ['display_name' => 'Owner', 'is_active' => true]);
        $owner->roles()->attach($ownerRole->id);

        $this->actingAs($this->adminUser);

        Livewire::test(CreateUserMealBenefitProfile::class)
            ->fillForm([
                'user_id' => $owner->id,
                'benefit_mode' => UserMealBenefitProfile::BENEFIT_MODE_OWNER_CHARGE,
                'is_active' => true,
                'notes' => 'Charge-enabled owner account',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $profile = UserMealBenefitProfile::query()->where('user_id', $owner->id)->first();

        $this->assertNotNull($profile);
        $this->assertTrue($profile->can_receive_owner_charge_orders);
        $this->assertFalse($profile->monthly_allowance_enabled);
        $this->assertFalse($profile->free_meal_enabled);
    }

    public function test_admin_can_configure_monthly_allowance_profile(): void
    {
        $employee = User::factory()->create([
            'name' => 'Allowance Employee',
            'username' => 'allowance-employee',
            'is_active' => true,
        ]);

        $profile = UserMealBenefitProfile::query()->create([
            'user_id' => $employee->id,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(EditUserMealBenefitProfile::class, ['record' => $profile->getRouteKey()])
            ->fillForm([
                'user_id' => $employee->id,
                'benefit_mode' => UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE,
                'monthly_allowance_amount' => 850.50,
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $profile->refresh();

        $this->assertTrue($profile->monthly_allowance_enabled);
        $this->assertSame('850.50', $profile->monthly_allowance_amount);
        $this->assertFalse($profile->can_receive_owner_charge_orders);
        $this->assertFalse($profile->free_meal_enabled);
    }

    public function test_admin_can_configure_free_meal_rule_and_assign_allowed_items(): void
    {
        $employee = User::factory()->create([
            'name' => 'Free Meal Employee',
            'username' => 'free-meal-employee',
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'name' => 'وجبات الموظفين',
            'is_active' => true,
        ]);
        $allowedItem = MenuItem::query()->create([
            'category_id' => $category->id,
            'name' => 'وجبة موظف',
            'type' => 'simple',
            'base_price' => 45,
            'is_available' => true,
            'is_active' => true,
        ]);
        $secondAllowedItem = MenuItem::query()->create([
            'category_id' => $category->id,
            'name' => 'ساندوتش موظف',
            'type' => 'simple',
            'base_price' => 30,
            'is_available' => true,
            'is_active' => true,
        ]);

        $profile = UserMealBenefitProfile::query()->create([
            'user_id' => $employee->id,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(EditUserMealBenefitProfile::class, ['record' => $profile->getRouteKey()])
            ->fillForm([
                'user_id' => $employee->id,
                'benefit_mode' => UserMealBenefitProfile::BENEFIT_MODE_FREE_MEAL,
                'free_meal_type' => 'count',
                'free_meal_monthly_count' => 12,
                'allowedMenuItems' => [$allowedItem->id, $secondAllowedItem->id],
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $profile->refresh();

        $this->assertTrue($profile->free_meal_enabled);
        $this->assertSame('count', $profile->free_meal_type?->value);
        $this->assertSame(12, $profile->free_meal_monthly_count);
        $this->assertEqualsCanonicalizing(
            [$allowedItem->id, $secondAllowedItem->id],
            $profile->allowedMenuItems()->pluck('menu_items.id')->all(),
        );
    }

    public function test_admin_can_bulk_assign_meal_benefits_to_multiple_users(): void
    {
        $firstEmployee = User::factory()->create([
            'name' => 'Bulk Employee 1',
            'username' => 'bulk-employee-1',
            'is_active' => true,
        ]);
        $secondEmployee = User::factory()->create([
            'name' => 'Bulk Employee 2',
            'username' => 'bulk-employee-2',
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(ListUserMealBenefitProfiles::class)
            ->callAction('bulkAssign', data: [
                'user_ids' => [$firstEmployee->id, $secondEmployee->id],
                'benefit_mode' => UserMealBenefitProfile::BENEFIT_MODE_MONTHLY_ALLOWANCE,
                'monthly_allowance_amount' => 600,
                'is_active' => true,
                'notes' => 'Bulk assigned allowance',
            ]);

        $profiles = UserMealBenefitProfile::query()
            ->whereIn('user_id', [$firstEmployee->id, $secondEmployee->id])
            ->get()
            ->keyBy('user_id');

        $this->assertCount(2, $profiles);
        $this->assertTrue($profiles[$firstEmployee->id]->monthly_allowance_enabled);
        $this->assertTrue($profiles[$secondEmployee->id]->monthly_allowance_enabled);
        $this->assertSame('600.00', $profiles[$firstEmployee->id]->monthly_allowance_amount);
        $this->assertSame('600.00', $profiles[$secondEmployee->id]->monthly_allowance_amount);
        $this->assertSame('Bulk assigned allowance', $profiles[$firstEmployee->id]->notes);
    }

    public function test_admin_can_view_meal_benefit_statements_and_entries(): void
    {
        $employee = User::factory()->create([
            'name' => 'Statement Employee',
            'username' => 'statement-employee',
            'is_active' => true,
        ]);
        $profile = UserMealBenefitProfile::query()->create([
            'user_id' => $employee->id,
            'is_active' => true,
            'monthly_allowance_enabled' => true,
            'monthly_allowance_amount' => 500,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        app(MealBenefitLedgerService::class)->record(
            user: $employee,
            entryType: MealBenefitLedgerEntryType::MonthlyAllowanceUsage,
            amount: 125,
            profile: $profile,
            period: [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->endOfMonth()->toDateString(),
            ],
            notes: 'Allowance usage for statement test',
            actorId: $this->adminUser->id,
        );

        $this->actingAs($this->adminUser)
            ->get('/admin/user-meal-benefit-profiles')
            ->assertSuccessful()
            ->assertSee('مزايا الوجبات');

        $this->actingAs($this->adminUser)
            ->get('/admin/meal-benefits-report')
            ->assertSuccessful()
            ->assertSee('كشف بدلات الوجبات والتحميل')
            ->assertSee('Allowance usage for statement test')
            ->assertSee($employee->name);

        $this->assertSame(1, MealBenefitLedgerEntry::query()->count());
    }
}
