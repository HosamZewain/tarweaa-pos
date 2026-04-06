<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Resources\InventoryTransferResource\Pages\EditInventoryTransfer;
use App\Filament\Resources\PaymentTerminalResource\Pages\ListPaymentTerminals;
use App\Filament\Resources\PurchaseResource\Pages\EditPurchase;
use App\Filament\Resources\PurchaseResource\Pages\ViewPurchase;
use App\Filament\Resources\UserMealBenefitProfileResource\Pages\ListUserMealBenefitProfiles;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryTransfer;
use App\Models\PaymentTerminal;
use App\Models\Permission;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminActionAuthorizationSweepTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private InventoryLocation $warehouse;
    private InventoryLocation $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();
        $this->warehouse = InventoryLocation::query()->where('code', 'main_warehouse')->firstOrFail();
        $this->restaurant = InventoryLocation::query()->where('code', 'restaurant')->firstOrFail();
    }

    public function test_user_without_inventory_transfer_update_permission_cannot_see_transfer_workflow_actions(): void
    {
        $user = $this->makeUserWithPermissions([
            'inventory_transfers.viewAny',
            'inventory_transfers.view',
        ]);

        $draftTransfer = $this->createTransfer('draft');
        $sentTransfer = $this->createTransfer('sent');

        Livewire::actingAs($user)
            ->test(EditInventoryTransfer::class, ['record' => $draftTransfer->getRouteKey()])
            ->assertDontSee('اعتماد')
            ->assertDontSee('إرسال التحويل')
            ->assertDontSee('إلغاء');

        Livewire::actingAs($user)
            ->test(EditInventoryTransfer::class, ['record' => $sentTransfer->getRouteKey()])
            ->assertDontSee('استلام التحويل');
    }

    public function test_user_with_inventory_transfer_update_permission_can_see_transfer_workflow_actions(): void
    {
        $user = $this->makeUserWithPermissions([
            'inventory_transfers.viewAny',
            'inventory_transfers.view',
            'inventory_transfers.update',
            'inventory_transfers.delete',
        ]);

        $draftTransfer = $this->createTransfer('draft');
        $sentTransfer = $this->createTransfer('sent');

        Livewire::actingAs($user)
            ->test(EditInventoryTransfer::class, ['record' => $draftTransfer->getRouteKey()])
            ->assertSee('اعتماد')
            ->assertSee('إرسال التحويل')
            ->assertSee('إلغاء');

        Livewire::actingAs($user)
            ->test(EditInventoryTransfer::class, ['record' => $sentTransfer->getRouteKey()])
            ->assertSee('استلام التحويل');
    }

    public function test_user_without_purchase_update_permission_cannot_see_receive_purchase_actions(): void
    {
        $user = $this->makeUserWithPermissions([
            'purchases.viewAny',
            'purchases.view',
        ]);

        $purchase = $this->createPendingPurchase();

        Livewire::actingAs($user)
            ->test(EditPurchase::class, ['record' => $purchase->getRouteKey()])
            ->assertDontSee('استلام كامل الشراء');

        Livewire::actingAs($user)
            ->test(ViewPurchase::class, ['record' => $purchase->getRouteKey()])
            ->assertDontSee('استلام كامل الشراء');
    }

    public function test_user_with_purchase_update_permission_can_see_receive_purchase_actions(): void
    {
        $user = $this->makeUserWithPermissions([
            'purchases.viewAny',
            'purchases.view',
            'purchases.update',
        ]);

        $purchase = $this->createPendingPurchase();

        Livewire::actingAs($user)
            ->test(EditPurchase::class, ['record' => $purchase->getRouteKey()])
            ->assertSee('استلام كامل الشراء');

        Livewire::actingAs($user)
            ->test(ViewPurchase::class, ['record' => $purchase->getRouteKey()])
            ->assertSee('استلام كامل الشراء');
    }

    public function test_user_without_meal_benefit_bulk_assign_permissions_cannot_see_bulk_assign_action(): void
    {
        $user = $this->makeUserWithPermissions([
            'user_meal_benefit_profiles.viewAny',
        ]);

        Livewire::actingAs($user)
            ->test(ListUserMealBenefitProfiles::class)
            ->assertActionHidden('bulkAssign');
    }

    public function test_user_with_meal_benefit_bulk_assign_permissions_can_see_bulk_assign_action(): void
    {
        $user = $this->makeUserWithPermissions([
            'user_meal_benefit_profiles.viewAny',
            'user_meal_benefit_profiles.create',
            'user_meal_benefit_profiles.update',
        ]);

        Livewire::actingAs($user)
            ->test(ListUserMealBenefitProfiles::class)
            ->assertActionVisible('bulkAssign');
    }

    public function test_user_without_update_permission_cannot_see_toggle_actions_on_user_employee_and_payment_terminal_lists(): void
    {
        $adminTarget = User::factory()->create([
            'name' => 'Admin Target User',
            'username' => 'admin-target-user',
            'is_active' => true,
        ]);

        $employeeUser = User::factory()->create([
            'name' => 'Employee Toggle Target',
            'username' => 'employee-toggle-target',
            'is_active' => true,
        ]);
        $employeeUser->roles()->sync([Role::query()->where('name', 'cashier')->firstOrFail()->id]);
        $employeeTarget = Employee::query()->findOrFail($employeeUser->id);

        $terminal = PaymentTerminal::create([
            'name' => 'Terminal Toggle Target',
            'bank_name' => 'Test Bank',
            'code' => 'TERM-' . fake()->unique()->numerify('####'),
            'fee_type' => 'percentage',
            'fee_percentage' => 1.5,
            'fee_fixed_amount' => 0,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $user = $this->makeUserWithPermissions([
            'users.viewAny',
            'employees.viewAny',
            'payment_terminals.viewAny',
        ]);

        Livewire::actingAs($user)
            ->test(ListUsers::class)
            ->assertDontSee('تعطيل')
            ->assertDontSee('تفعيل');

        Livewire::actingAs($user)
            ->test(ListEmployees::class)
            ->assertDontSee('تعطيل')
            ->assertDontSee('تفعيل');

        Livewire::actingAs($user)
            ->test(ListPaymentTerminals::class)
            ->assertDontSee('إيقاف')
            ->assertDontSee('تفعيل');
    }

    private function makeUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create([
            'name' => 'Permission Sweep User',
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'permission-sweep-' . fake()->unique()->slug(2),
            'display_name' => 'Permission Sweep Role',
            'is_active' => true,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function createTransfer(string $status): InventoryTransfer
    {
        return InventoryTransfer::create([
            'source_location_id' => $this->warehouse->id,
            'destination_location_id' => $this->restaurant->id,
            'requested_by' => $this->adminUser->id,
            'status' => $status,
            'approved_by' => $status !== 'draft' ? $this->adminUser->id : null,
            'approved_at' => $status !== 'draft' ? now()->subMinutes(10) : null,
            'sent_at' => $status === 'sent' ? now()->subMinutes(5) : null,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
            'notes' => 'Transfer auth sweep',
        ]);
    }

    private function createPendingPurchase(): Purchase
    {
        $supplier = Supplier::create([
            'name' => 'Permission Sweep Supplier',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $item = InventoryItem::create([
            'name' => 'Permission Sweep Item',
            'unit' => 'قطعة',
            'unit_cost' => 20,
            'current_stock' => 0,
            'minimum_stock' => 1,
            'maximum_stock' => 50,
            'is_active' => true,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'destination_location_id' => $this->warehouse->id,
            'status' => 'ordered',
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'inventory_item_id' => $item->id,
            'unit' => 'قطعة',
            'unit_price' => 20,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'total' => 100,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        return $purchase->fresh();
    }
}
