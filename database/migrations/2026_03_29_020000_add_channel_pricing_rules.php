<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_types', function (Blueprint $table): void {
            $table->string('pricing_rule_type')->default('base_price')->after('source');
            $table->decimal('pricing_rule_value', 10, 2)->default(0)->after('pricing_rule_type');
        });

        Schema::create('menu_item_channel_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_order_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('menu_item_variant_id')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedBigInteger('variant_scope_id')->storedAs('IFNULL(menu_item_variant_id, 0)');
            $table->timestamps();

            $table->index('menu_item_variant_id');
            $table->unique(
                ['menu_item_id', 'pos_order_type_id', 'variant_scope_id'],
                'menu_item_channel_prices_unique_scope'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_channel_prices');

        Schema::table('pos_order_types', function (Blueprint $table): void {
            $table->dropColumn(['pricing_rule_type', 'pricing_rule_value']);
        });
    }
};
