<?php

namespace Tests\Feature;

use App\Enums\PaymentTerminalFeeType;
use App\Filament\Resources\PaymentTerminalResource\Pages\CreatePaymentTerminal;
use App\Filament\Resources\PaymentTerminalResource\Pages\EditPaymentTerminal;
use App\Models\PaymentTerminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentTerminalAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_payment_terminal_from_filament(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(CreatePaymentTerminal::class)
            ->fillForm([
                'name' => 'CIB Front',
                'bank_name' => 'CIB',
                'code' => 'CIB-FRONT-1',
                'fee_type' => PaymentTerminalFeeType::PercentagePlusFixed->value,
                'fee_percentage' => 2.5,
                'fee_fixed_amount' => 1.5,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('payment_terminals', [
            'name' => 'CIB Front',
            'bank_name' => 'CIB',
            'code' => 'CIB-FRONT-1',
            'fee_type' => PaymentTerminalFeeType::PercentagePlusFixed->value,
            'fee_percentage' => '2.5000',
            'fee_fixed_amount' => '1.50',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_edit_payment_terminal_fee_configuration(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();

        $terminal = PaymentTerminal::create([
            'name' => 'QNB Back',
            'bank_name' => 'QNB',
            'code' => 'QNB-BACK-1',
            'fee_type' => PaymentTerminalFeeType::Percentage->value,
            'fee_percentage' => 2,
            'fee_fixed_amount' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(EditPaymentTerminal::class, ['record' => $terminal->getRouteKey()])
            ->fillForm([
                'name' => 'QNB Back Updated',
                'bank_name' => 'QNB',
                'code' => 'QNB-BACK-1',
                'fee_type' => PaymentTerminalFeeType::Fixed->value,
                'fee_percentage' => 0,
                'fee_fixed_amount' => 3,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('payment_terminals', [
            'id' => $terminal->id,
            'name' => 'QNB Back Updated',
            'fee_type' => PaymentTerminalFeeType::Fixed->value,
            'fee_percentage' => '0.0000',
            'fee_fixed_amount' => '3.00',
            'is_active' => false,
        ]);
    }
}
