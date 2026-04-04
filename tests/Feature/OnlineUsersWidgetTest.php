<?php

namespace Tests\Feature;

use App\Filament\Widgets\OnlineUsersWidget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OnlineUsersWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_users_widget_counts_recent_sessions_and_labels_last_activity(): void
    {
        $this->artisan('db:seed');

        $admin = User::factory()->create([
            'name' => 'Admin Online',
            'email' => 'admin.online@example.com',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create([
            'name' => 'Cashier Online',
            'email' => 'cashier.online@example.com',
            'is_active' => true,
        ]);

        $oldUser = User::factory()->create([
            'name' => 'Old Session',
            'email' => 'old.session@example.com',
            'is_active' => true,
        ]);

        $inactiveUser = User::factory()->create([
            'name' => 'Inactive Online',
            'email' => 'inactive.online@example.com',
            'is_active' => false,
        ]);

        $admin->roles()->sync([Role::firstWhere('name', 'admin')->id]);
        $cashier->roles()->sync([Role::firstWhere('name', 'cashier')->id]);
        $oldUser->roles()->sync([Role::firstWhere('name', 'cashier')->id]);
        $inactiveUser->roles()->sync([Role::firstWhere('name', 'manager')->id]);

        $now = now()->timestamp;

        DB::table('sessions')->insert([
            [
                'id' => 'admin-online-session',
                'user_id' => $admin->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'test',
                'last_activity' => $now - 120,
            ],
            [
                'id' => 'cashier-online-session',
                'user_id' => $cashier->id,
                'ip_address' => '127.0.0.2',
                'user_agent' => 'PHPUnit',
                'payload' => 'test',
                'last_activity' => $now - (17 * 60),
            ],
            [
                'id' => 'old-online-session',
                'user_id' => $oldUser->id,
                'ip_address' => '127.0.0.3',
                'user_agent' => 'PHPUnit',
                'payload' => 'test',
                'last_activity' => $now - (90 * 60),
            ],
            [
                'id' => 'inactive-online-session',
                'user_id' => $inactiveUser->id,
                'ip_address' => '127.0.0.4',
                'user_agent' => 'PHPUnit',
                'payload' => 'test',
                'last_activity' => $now - 60,
            ],
        ]);

        $this->actingAs($admin);

        $data = app(OnlineUsersWidget::class)->getViewData();

        $this->assertCount(2, $data['onlineUsers']);
        $this->assertSame(2, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['recently_active']);
        $this->assertSame(1, $data['summary']['privileged']);
        $this->assertSame(1, $data['summary']['operational']);
        $this->assertSame('Admin Online', $data['onlineUsers'][0]['name']);
        $this->assertSame('نشط الآن', $data['onlineUsers'][0]['status_label']);
        $this->assertSame('Cashier Online', $data['onlineUsers'][1]['name']);
        $this->assertSame('منذ 17 دقيقة', $data['onlineUsers'][1]['last_activity_label']);
    }

    public function test_online_users_widget_renders_section_labels(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        DB::table('sessions')->insert([
            'id' => 'dashboard-online-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => now()->timestamp - 60,
        ]);

        $this->actingAs($admin);

        Livewire::test(OnlineUsersWidget::class)
            ->assertSee('المتصلون خلال آخر ساعة')
            ->assertSee('إجمالي المتصلين');
    }
}
