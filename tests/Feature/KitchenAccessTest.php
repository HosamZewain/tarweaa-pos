<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_unified_login_for_kitchen(): void
    {
        $this->get('/kitchen')
            ->assertRedirect('/?redirect=%2Fkitchen');
    }

    public function test_kitchen_login_page_accepts_redirect_target(): void
    {
        $this->get('/pos/login?redirect=%2Fkitchen')
            ->assertSuccessful();
    }
}
