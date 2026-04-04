<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('item_type', 50)->default('raw_material')->after('category');
        });

        Schema::create('production_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepared_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->decimal('output_quantity', 12, 3);
            $table->string('output_unit', 20);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('prepared_item_id');
        });

        Schema::create('production_recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_recipe_id')->constrained('production_recipes')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20);
            $table->decimal('unit_conversion_rate', 12, 6)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->foreignId('prepared_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('production_recipe_id')->nullable()->constrained('production_recipes')->nullOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('status', 20)->default('completed')->index();
            $table->decimal('planned_output_quantity', 12, 3)->default(0);
            $table->decimal('actual_output_quantity', 12, 3);
            $table->string('output_unit', 20);
            $table->decimal('total_input_cost', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('yield_variance_quantity', 12, 3)->default(0);
            $table->decimal('yield_variance_percentage', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('produced_at')->nullable();
            $table->foreignId('produced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained('production_batches')->cascadeOnDelete();
            $table->foreignId('production_recipe_line_id')->nullable()->constrained('production_recipe_lines')->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete();
            $table->decimal('planned_quantity', 12, 3)->default(0);
            $table->decimal('actual_quantity', 12, 3);
            $table->decimal('base_quantity', 12, 6);
            $table->string('unit', 20);
            $table->decimal('unit_conversion_rate', 12, 6)->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_transactions
            MODIFY COLUMN type ENUM(
                'purchase',
                'sale_deduction',
                'adjustment',
                'waste',
                'return',
                'transfer_in',
                'transfer_out',
                'production_consumption',
                'production_output'
            ) NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_transactions
            MODIFY COLUMN type ENUM(
                'purchase',
                'sale_deduction',
                'adjustment',
                'waste',
                'return',
                'transfer_in',
                'transfer_out'
            ) NOT NULL
        SQL);

        Schema::dropIfExists('production_batch_lines');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('production_recipe_lines');
        Schema::dropIfExists('production_recipes');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};
