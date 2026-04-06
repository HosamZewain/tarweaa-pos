<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpenseResource\Pages\EditExpense;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseBulkDeleteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_user_without_expense_delete_permission_cannot_see_bulk_delete_action(): void
    {
        $user = $this->makeExpenseUser([
            'expenses.viewAny',
        ]);

        Livewire::actingAs($user)
            ->test(ListExpenses::class)
            ->assertTableBulkActionHidden('delete');
    }

    public function test_user_with_expense_delete_permission_can_see_bulk_delete_action(): void
    {
        $user = $this->makeExpenseUser([
            'expenses.viewAny',
            'expenses.delete',
        ]);

        Livewire::actingAs($user)
            ->test(ListExpenses::class)
            ->assertTableBulkActionVisible('delete');
    }

    public function test_user_without_expense_delete_permission_cannot_see_delete_action_on_edit_page(): void
    {
        $user = $this->makeExpenseUser([
            'expenses.viewAny',
            'expenses.update',
        ]);

        $expense = $this->createExpense();

        Livewire::actingAs($user)
            ->test(EditExpense::class, ['record' => $expense->getRouteKey()])
            ->assertActionHidden('delete');
    }

    public function test_user_with_expense_delete_permission_can_see_delete_action_on_edit_page(): void
    {
        $user = $this->makeExpenseUser([
            'expenses.viewAny',
            'expenses.update',
            'expenses.delete',
        ]);

        $expense = $this->createExpense();

        Livewire::actingAs($user)
            ->test(EditExpense::class, ['record' => $expense->getRouteKey()])
            ->assertActionVisible('delete');
    }

    private function makeExpenseUser(array $permissionNames): User
    {
        $user = User::factory()->create([
            'name' => 'Expense Permission User',
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'expense-permission-' . fake()->unique()->slug(2),
            'display_name' => 'Expense Permission Role',
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

    private function createExpense(): Expense
    {
        $category = ExpenseCategory::query()->first()
            ?? ExpenseCategory::create([
                'name' => 'اختبار',
                'description' => 'تصنيف اختبار',
                'is_active' => true,
            ]);

        return Expense::create([
            'expense_number' => 'EXP-TEST-' . fake()->unique()->numerify('####'),
            'category_id' => $category->id,
            'amount' => 150,
            'description' => 'Test expense',
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
            'created_by' => User::where('email', 'admin@pos.com')->firstOrFail()->id,
            'updated_by' => User::where('email', 'admin@pos.com')->firstOrFail()->id,
        ]);
    }
}
