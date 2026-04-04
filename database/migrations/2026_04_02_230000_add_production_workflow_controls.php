<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->boolean('is_default_production_location')
                ->default(false)
                ->after('is_default_recipe_deduction_location');
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->decimal('waste_quantity', 12, 3)->default(0)->after('actual_output_quantity');
            $table->text('waste_notes')->nullable()->after('notes');
            $table->foreignId('approved_by')->nullable()->after('produced_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('voided_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');
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
                'production_output',
                'production_void_output',
                'production_void_input_return'
            ) NOT NULL
        SQL);

        $hasDefault = DB::table('inventory_locations')
            ->where('is_active', true)
            ->where('is_default_production_location', true)
            ->exists();

        if (!$hasDefault) {
            $restaurantId = DB::table('inventory_locations')
                ->where('code', 'restaurant')
                ->value('id');

            if ($restaurantId) {
                DB::table('inventory_locations')
                    ->where('id', $restaurantId)
                    ->update(['is_default_production_location' => true]);
            }
        }
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
                'transfer_out',
                'production_consumption',
                'production_output'
            ) NOT NULL
        SQL);

        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn([
                'waste_quantity',
                'waste_notes',
                'approved_at',
                'voided_at',
                'void_reason',
            ]);
        });

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropColumn('is_default_production_location');
        });
    }
};
