<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_does_not_reset_existing_admin_credentials(): void
    {
        $this->artisan('db:seed');

        $admin = User::where('email', 'admin@pos.com')->firstOrFail();
        $admin->update([
            'username' => 'custom-admin',
            'password' => Hash::make('custom-secret'),
            'pin' => '9876',
            'phone' => '01111111111',
        ]);

        $this->artisan('db:seed');

        $admin->refresh();

        $this->assertSame('custom-admin', $admin->username);
        $this->assertSame('9876', $admin->pin);
        $this->assertSame('01111111111', $admin->phone);
        $this->assertTrue(Hash::check('custom-secret', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }
}
