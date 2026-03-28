<?php

namespace Tests\Feature;

use App\Enums\DrawerSessionStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Models\CashierDrawerSession;
use App\Models\ExpenseCategory;
use App\Models\Expense;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_can_create_database_backup_file(): void
    {
        Storage::fake('local');

        MenuCategory::create([
            'name' => 'فطور',
            'is_active' => true,
        ]);

        $backup = app(DatabaseBackupService::class)->createBackup('test-export');

        Storage::disk('local')->assertExists($backup['path']);

        $contents = Storage::disk('local')->get($backup['path']);

        $this->assertStringContainsString('CREATE TABLE', $contents);
        $this->assertStringContainsString('menu_categories', $contents);
        $this->assertStringContainsString('فطور', $contents);
    }

    public function test_service_can_restore_database_from_generated_backup(): void
    {
        $service = app(DatabaseBackupService::class);

        MenuCategory::create([
            'name' => 'الأصلية',
            'is_active' => true,
        ]);

        $backup = $service->createBackup('restore-test');

        MenuCategory::create([
            'name' => 'بعد النسخة',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('menu_categories', ['name' => 'بعد النسخة']);

        $service->restoreBackup($backup['path'], createSafetyBackup: false);

        $this->assertDatabaseHas('menu_categories', ['name' => 'الأصلية']);
        $this->assertDatabaseMissing('menu_categories', ['name' => 'بعد النسخة']);
    }

    public function test_service_can_reset_operational_data_while_preserving_users_and_menu(): void
    {
        $service = app(DatabaseBackupService::class);

        $user = User::factory()->create([
            'name' => 'Operational User',
            'is_active' => true,
        ]);

        $category = MenuCategory::create([
            'name' => 'فطور',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-RESET-001',
            'status' => ShiftStatus::Open,
            'opened_by' => $user->id,
            'started_at' => now(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Reset',
            'identifier' => 'POS-RESET-001',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRW-RESET-001',
            'cashier_id' => $user->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $user->id,
            'opening_balance' => 100,
            'status' => DrawerSessionStatus::Open,
            'started_at' => now(),
        ]);

        $expenseCategory = ExpenseCategory::create([
            'name' => 'تشغيلية',
            'is_active' => true,
        ]);

        Order::create([
            'order_number' => 'ORD-RESET-0001',
            'type' => OrderType::Pickup,
            'status' => OrderStatus::Confirmed,
            'source' => OrderSource::Pos,
            'cashier_id' => $user->id,
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'customer_name' => 'عميل',
            'subtotal' => 50,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'total' => 50,
            'payment_status' => PaymentStatus::Paid,
            'paid_amount' => 50,
            'change_amount' => 0,
            'refund_amount' => 0,
        ]);

        Expense::create([
            'expense_number' => 'EXP-RESET-001',
            'category_id' => $expenseCategory->id,
            'amount' => 20,
            'description' => 'مصروف تجريبي',
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
            'shift_id' => $shift->id,
            'drawer_session_id' => $drawer->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $service->resetOperationalData(createSafetyBackup: false);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('cashier_drawer_sessions', 0);
        $this->assertDatabaseCount('shifts', 0);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('menu_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('pos_devices', ['id' => $device->id]);
    }

    public function test_admin_can_restore_backup_via_standard_upload_route(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();
        $adminRole = Role::firstWhere('name', 'admin');

        if ($adminRole && !$admin->roles->contains($adminRole->id)) {
            $admin->roles()->attach($adminRole->id);
        }

        $service = app(DatabaseBackupService::class);

        MenuCategory::create([
            'name' => 'قبل الاستعادة',
            'is_active' => true,
        ]);

        $backup = $service->createBackup('route-restore');
        $backupContents = Storage::disk('local')->get($backup['path']);

        MenuCategory::create([
            'name' => 'بعد النسخة',
            'is_active' => true,
        ]);

        $uploadedFile = UploadedFile::fake()->createWithContent('restore-backup.sql', $backupContents);

        $this->actingAs($admin)
            ->post(route('admin.database-backups.restore'), [
                'restore_backup_file' => $uploadedFile,
                'restore_confirmation' => 'RESTORE',
            ])
            ->assertRedirect(route('filament.admin.pages.database-backups-page'));

        $this->assertDatabaseHas('menu_categories', ['name' => 'قبل الاستعادة']);
        $this->assertDatabaseMissing('menu_categories', ['name' => 'بعد النسخة']);
    }

    public function test_restore_upload_route_requires_database_backup_permission(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $uploadedFile = UploadedFile::fake()->createWithContent('restore-backup.sql', '-- empty');

        $this->actingAs($user)
            ->post(route('admin.database-backups.restore'), [
                'restore_backup_file' => $uploadedFile,
                'restore_confirmation' => 'RESTORE',
            ])
            ->assertForbidden();
    }
}
