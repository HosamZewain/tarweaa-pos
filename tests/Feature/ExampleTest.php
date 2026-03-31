<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_root_shows_unified_login_portal_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertSuccessful()
            ->assertSee('بوابة الدخول الموحدة')
            ->assertSee('Tarweaa');
    }
}
