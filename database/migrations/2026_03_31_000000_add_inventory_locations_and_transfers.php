<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->enum('type', ['warehouse', 'restaurant', 'other'])->default('other')->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default_purchase_destination')->default(false);
            $table->boolean('is_default_recipe_deduction_location')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_location_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('inventory_location_id')->constrained('inventory_locations');
            $table->decimal('current_stock', 10, 3)->default(0.000);
            $table->decimal('minimum_stock', 10, 3)->default(0.000);
            $table->decimal('maximum_stock', 10, 3)->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['inventory_item_id', 'inventory_location_id'], 'inventory_item_location_unique');
            $table->index(['inventory_location_id', 'current_stock'], 'inv_loc_stock_loc_stock_idx');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('inventory_location_id')
                ->nullable()
                ->after('inventory_item_id')
                ->constrained('inventory_locations');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('destination_location_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('inventory_locations');
        });

        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('source_location_id')->constrained('inventory_locations');
            $table->foreignId('destination_location_id')->constrained('inventory_locations');
            $table->enum('status', ['draft', 'sent', 'received', 'cancelled'])->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained('inventory_transfers');
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->string('unit', 50);
            $table->decimal('quantity_sent', 10, 3)->default(0.000);
            $table->decimal('quantity_received', 10, 3)->default(0.000);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        $now = Carbon::now();

        DB::table('inventory_locations')->insert([
            [
                'code' => 'main_warehouse',
                'name' => 'المخزن الرئيسي',
                'type' => 'warehouse',
                'is_active' => true,
                'is_default_purchase_destination' => false,
                'is_default_recipe_deduction_location' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'restaurant',
                'name' => 'المطعم',
                'type' => 'restaurant',
                'is_active' => true,
                'is_default_purchase_destination' => true,
                'is_default_recipe_deduction_location' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $restaurantLocationId = (int) DB::table('inventory_locations')
            ->where('code', 'restaurant')
            ->value('id');

        $itemRows = DB::table('inventory_items')
            ->select(['id', 'current_stock', 'minimum_stock', 'maximum_stock', 'unit_cost', 'created_by', 'updated_by', 'created_at', 'updated_at'])
            ->get()
            ->map(fn ($item) => [
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $restaurantLocationId,
                'current_stock' => $item->current_stock,
                'minimum_stock' => $item->minimum_stock,
                'maximum_stock' => $item->maximum_stock,
                'unit_cost' => $item->unit_cost,
                'created_by' => $item->created_by,
                'updated_by' => $item->updated_by,
                'created_at' => $item->created_at ?? $now,
                'updated_at' => $item->updated_at ?? $now,
            ])
            ->all();

        if (!empty($itemRows)) {
            DB::table('inventory_location_stocks')->insert($itemRows);
        }

        DB::table('purchases')->update([
            'destination_location_id' => $restaurantLocationId,
        ]);

        DB::table('inventory_transactions')
            ->whereNull('inventory_location_id')
            ->update([
                'inventory_location_id' => $restaurantLocationId,
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
        Schema::dropIfExists('inventory_transfers');

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_location_id');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_location_id');
        });

        Schema::dropIfExists('inventory_location_stocks');
        Schema::dropIfExists('inventory_locations');
    }
};
