<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE order_payments
            MODIFY payment_method ENUM('cash', 'card', 'online', 'instapay', 'talabat_pay', 'jahez_pay', 'other')
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE order_payments
            MODIFY payment_method ENUM('cash', 'card', 'online', 'talabat_pay', 'jahez_pay', 'other')
            NOT NULL
        ");
    }
};
