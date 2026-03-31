<?php

namespace Tests\Feature;

use App\Filament\Resources\InventoryLocationResource\Pages\EditInventoryLocation;
use App\Models\InventoryLocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryLocationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->first()
            ?? User::factory()->create([
                'email' => 'admin@pos.com',
                'username' => 'admin-user',
                'is_active' => true,
            ]);

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator'],
        );

        $this->adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
    }

    public function test_admin_cannot_deactivate_only_default_recipe_location(): void
    {
        $restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();

        Livewire::actingAs($this->adminUser)
            ->test(EditInventoryLocation::class, ['record' => $restaurant->getRouteKey()])
            ->fillForm([
                'is_active' => false,
                'is_default_recipe_deduction_location' => false,
                'is_default_purchase_destination' => false,
            ])
            ->call('save')
            ->assertHasFormErrors(['is_default_purchase_destination', 'is_default_recipe_deduction_location']);
    }
}
