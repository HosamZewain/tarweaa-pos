<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpenseResource\Pages\CreateExpense;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseCreationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->adminUser = User::where('email', 'admin@pos.com')->first()
            ?? User::factory()->create([
                'email' => 'admin@pos.com',
                'username' => 'admin-expense-user',
                'is_active' => true,
            ]);

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator'],
        );

        $this->adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
    }

    public function test_admin_can_create_card_expense_from_filament_form(): void
    {
        $category = ExpenseCategory::query()->first()
            ?? ExpenseCategory::create([
                'name' => 'تشغيل',
                'description' => 'تصنيف اختبار',
                'is_active' => true,
            ]);

        $shift = Shift::query()->create([
            'shift_number' => 'SHIFT-EXP-001',
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'started_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(CreateExpense::class)
            ->fillForm([
                'category_id' => $category->id,
                'amount' => 125.5,
                'description' => 'مصروف بطاقة اختبار',
                'payment_method' => 'card',
                'receipt_number' => 'CARD-EXP-001',
                'expense_date' => now()->toDateString(),
                'shift_id' => $shift->id,
                'notes' => 'اختبار إنشاء مصروف بالبطاقة',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', [
            'category_id' => $category->id,
            'amount' => '125.50',
            'description' => 'مصروف بطاقة اختبار',
            'payment_method' => 'card',
            'receipt_number' => 'CARD-EXP-001',
            'shift_id' => $shift->id,
        ]);

        $expense = Expense::query()->latest('id')->firstOrFail();

        $this->assertNotEmpty($expense->expense_number);
        $this->assertSame($this->adminUser->id, $expense->created_by);
        $this->assertSame($this->adminUser->id, $expense->updated_by);
    }
}
