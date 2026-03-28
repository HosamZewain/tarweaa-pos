<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_meal_benefit_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('can_receive_owner_charge_orders')->default(false)->index();
            $table->boolean('monthly_allowance_enabled')->default(false);
            $table->decimal('monthly_allowance_amount', 12, 2)->default(0);
            $table->boolean('free_meal_enabled')->default(false);
            $table->string('free_meal_type', 50)->nullable();
            $table->unsignedInteger('free_meal_monthly_count')->nullable();
            $table->decimal('free_meal_monthly_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('user_meal_benefit_profile_menu_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('user_meal_benefit_profiles')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items');
            $table->timestamps();
            $table->unique(['profile_id', 'menu_item_id'], 'meal_benefit_profile_menu_item_unique');
        });

        Schema::create('order_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('settlement_type', 50)->default('standard')->index();
            $table->foreignId('beneficiary_user_id')->nullable()->constrained('users');
            $table->foreignId('charge_account_user_id')->nullable()->constrained('users');
            $table->decimal('commercial_total_amount', 12, 2)->default(0);
            $table->decimal('covered_amount', 12, 2)->default(0);
            $table->decimal('remaining_payable_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('order_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_settlement_id')->constrained('order_settlements')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('line_type', 80)->index();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('profile_id')->nullable()->constrained('user_meal_benefit_profiles');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->decimal('eligible_amount', 12, 2)->default(0);
            $table->decimal('covered_amount', 12, 2)->default(0);
            $table->unsignedInteger('covered_quantity')->nullable();
            $table->date('benefit_period_start')->nullable()->index();
            $table->date('benefit_period_end')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('meal_benefit_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('profile_id')->nullable()->constrained('user_meal_benefit_profiles');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_settlement_line_id')->nullable()->constrained('order_settlement_lines')->nullOnDelete();
            $table->string('entry_type', 80)->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->integer('meals_count')->default(0);
            $table->date('benefit_period_start')->nullable()->index();
            $table->date('benefit_period_end')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_benefit_ledger_entries');
        Schema::dropIfExists('order_settlement_lines');
        Schema::dropIfExists('order_settlements');
        Schema::dropIfExists('user_meal_benefit_profile_menu_item');
        Schema::dropIfExists('user_meal_benefit_profiles');
    }
};
