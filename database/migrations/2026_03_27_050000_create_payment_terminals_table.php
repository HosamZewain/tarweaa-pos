<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('bank_name', 255)->nullable();
            $table->string('code', 100)->nullable()->unique();
            $table->enum('fee_type', ['percentage', 'fixed', 'percentage_plus_fixed'])->default('percentage');
            $table->decimal('fee_percentage', 8, 4)->default(0);
            $table->decimal('fee_fixed_amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_terminals');
    }
};
