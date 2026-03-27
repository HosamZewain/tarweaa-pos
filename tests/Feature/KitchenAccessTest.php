<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_load_kitchen_shell(): void
    {
        $this->get('/kitchen')
            ->assertSuccessful()
            ->assertSee('رقم الطلب')
            ->assertSee('اضغط Enter للتجهيز');
    }

    public function test_kitchen_login_page_accepts_redirect_target(): void
    {
        $this->get('/pos/login?redirect=%2Fkitchen')
            ->assertSuccessful();
    }
}
