<?php

namespace Tests\Feature;

use App\DTOs\CreateOrderData;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\PosOrderTypeResource\Pages\CreatePosOrderType;
use App\Models\CashierActiveSession;
use App\Models\CashierDrawerSession;
use App\Models\PosDevice;
use App\Models\PosOrderType;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\OrderCreationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class PosOrderTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
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

    public function test_admin_can_manage_pos_order_types(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/pos-order-types')
            ->assertSuccessful()
            ->assertSee('أنواع الطلبات');

        Livewire::actingAs($this->adminUser);

        Livewire::test(CreatePosOrderType::class)
            ->fillForm([
                'name' => 'سفري سريع',
                'type' => OrderType::Takeaway->value,
                'source' => OrderSource::Pos->value,
                'sort_order' => 10,
                'print_copies' => 3,
                'is_active' => true,
                'is_default' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = PosOrderType::query()->where('name', 'سفري سريع')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->is_default);
        $this->assertSame(3, $created->print_copies);
        $this->assertFalse(
            PosOrderType::query()
                ->whereKeyNot($created->id)
                ->where('is_default', true)
                ->exists()
        );
    }

    public function test_pos_endpoint_returns_only_active_non_deleted_types_and_default_first(): void
    {
        PosOrderType::query()->update(['is_default' => false]);

        $default = PosOrderType::query()->create([
            'name' => 'صالة',
            'type' => OrderType::Pickup->value,
            'source' => OrderSource::Pos->value,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 50,
            'print_copies' => 2,
        ]);

        PosOrderType::query()->create([
            'name' => 'مؤرشف',
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 1,
        ])->delete();

        PosOrderType::query()->create([
            'name' => 'غير نشط',
            'type' => OrderType::Delivery->value,
            'source' => OrderSource::Pos->value,
            'is_active' => false,
            'is_default' => false,
            'sort_order' => 2,
        ]);

        Sanctum::actingAs($this->adminUser->fresh());

        $response = $this->getJson('/api/pos/order-types')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($response);
        $this->assertSame($default->id, $response[0]['id']);
        $this->assertTrue($response[0]['is_default']);
        $this->assertSame(2, $response[0]['print_copies']);
        $this->assertFalse(collect($response)->contains(fn (array $type) => $type['name'] === 'مؤرشف'));
        $this->assertFalse(collect($response)->contains(fn (array $type) => $type['name'] === 'غير نشط'));
    }

    public function test_pos_order_types_endpoint_exposes_contextual_payment_method_for_external_channels(): void
    {
        PosOrderType::query()->create([
            'name' => 'طلبات أونلاين',
            'type' => OrderType::Delivery->value,
            'source' => OrderSource::Talabat->value,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 10,
        ]);

        PosOrderType::query()->create([
            'name' => 'طلبات قديم',
            'type' => OrderType::Delivery->value,
            'source' => 'طلبات',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 11,
        ]);

        PosOrderType::query()->create([
            'name' => 'جاهز',
            'type' => OrderType::Delivery->value,
            'source' => OrderSource::Jahez->value,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 12,
        ]);

        Sanctum::actingAs($this->adminUser->fresh());

        $response = collect($this->getJson('/api/pos/order-types')->assertOk()->json('data'));

        $this->assertSame(
            PaymentMethod::TalabatPay->value,
            $response->firstWhere('name', 'طلبات أونلاين')['contextual_payment_method'] ?? null,
        );

        $this->assertSame(
            PaymentMethod::TalabatPay->value,
            $response->firstWhere('name', 'طلبات قديم')['contextual_payment_method'] ?? null,
        );

        $this->assertSame(
            PaymentMethod::JahezPay->value,
            $response->firstWhere('name', 'جاهز')['contextual_payment_method'] ?? null,
        );
    }

    public function test_order_creation_stores_selected_pos_order_type_snapshot(): void
    {
        $cashier = User::factory()->create([
            'name' => 'POS Cashier',
            'is_active' => true,
        ]);

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier'],
            ['display_name' => 'Cashier', 'is_active' => true],
        );
        $cashier->roles()->attach($cashierRole->id);

        $shift = Shift::create([
            'shift_number' => 'SHIFT-POS-TYPE-001',
            'status' => 'open',
            'opened_by' => $cashier->id,
            'started_at' => now()->subHour(),
        ]);

        $device = PosDevice::create([
            'name' => 'POS Type Device',
            'identifier' => 'POS-TYPE-01',
            'is_active' => true,
        ]);

        $drawer = CashierDrawerSession::create([
            'session_number' => 'DRAWER-POS-TYPE-001',
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'pos_device_id' => $device->id,
            'opened_by' => $cashier->id,
            'opening_balance' => 100,
            'status' => 'open',
            'started_at' => now()->subHour(),
        ]);

        CashierActiveSession::create([
            'cashier_id' => $cashier->id,
            'drawer_session_id' => $drawer->id,
            'pos_device_id' => $device->id,
            'shift_id' => $shift->id,
        ]);

        $customType = PosOrderType::create([
            'name' => 'سفري VIP',
            'type' => OrderType::Takeaway->value,
            'source' => OrderSource::Pos->value,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $order = app(OrderCreationService::class)->create(
            $cashier,
            CreateOrderData::fromArray([
                'pos_order_type_id' => $customType->id,
                'type' => OrderType::Delivery->value,
                'source' => OrderSource::Other->value,
            ]),
        );

        $order->refresh();

        $this->assertSame($customType->id, $order->pos_order_type_id);
        $this->assertSame('سفري VIP', $order->order_type_name);
        $this->assertSame(OrderType::Takeaway, $order->type);
        $this->assertSame(OrderSource::Pos, $order->source);
        $this->assertSame('سفري VIP', $order->type_label);
    }
}
