<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 20)->default('order');
            $table->string('action', 40)->default('applied');
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('previous_discount_amount', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['scope', 'created_at']);
            $table->index(['applied_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_logs');
    }
};
