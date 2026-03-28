<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\AdminActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->adminUser = User::where('email', 'admin@pos.com')->firstOrFail();
    }

    public function test_admin_model_creation_is_logged(): void
    {
        $this->actingAs($this->adminUser);

        $user = User::create([
            'name' => 'Logged User',
            'username' => 'logged-user',
            'email' => 'logged-user@example.com',
            'password' => 'secret123',
            'pin' => '5678',
            'is_active' => true,
        ]);

        $log = AdminActivityLog::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'created')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->adminUser->id, $log->actor_user_id);
        $this->assertSame('users', $log->module);
        $this->assertSame('***', $log->new_values['password'] ?? null);
        $this->assertSame('***', $log->new_values['pin'] ?? null);
    }

    public function test_admin_model_update_is_logged_with_changed_values_only(): void
    {
        $this->actingAs($this->adminUser);

        $user = User::factory()->create([
            'name' => 'Before Name',
            'username' => 'before-name',
            'is_active' => true,
        ]);

        $user->update([
            'name' => 'After Name',
            'is_active' => false,
        ]);

        $log = AdminActivityLog::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame([
            'name' => 'Before Name',
            'is_active' => true,
        ], $log->old_values);
        $this->assertSame([
            'name' => 'After Name',
            'is_active' => false,
        ], $log->new_values);
    }

    public function test_manual_admin_actions_can_be_logged(): void
    {
        $this->actingAs($this->adminUser);

        app(AdminActivityLogService::class)->logAction(
            action: 'backup_created',
            description: 'تم إنشاء نسخة احتياطية تجريبية.',
            newValues: ['filename' => 'demo.sql'],
            module: 'settings.database_backups',
            subjectLabel: 'demo.sql',
        );

        $this->assertDatabaseHas('admin_activity_logs', [
            'actor_user_id' => $this->adminUser->id,
            'action' => 'backup_created',
            'module' => 'settings.database_backups',
            'subject_label' => 'demo.sql',
        ]);
    }
}
