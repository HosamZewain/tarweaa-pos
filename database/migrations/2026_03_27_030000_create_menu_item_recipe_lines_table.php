<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 50);
            $table->decimal('unit_conversion_rate', 12, 6)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['menu_item_id', 'sort_order']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('stock_deducted_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('stock_deducted_at');
        });

        Schema::dropIfExists('menu_item_recipe_lines');
    }
};
