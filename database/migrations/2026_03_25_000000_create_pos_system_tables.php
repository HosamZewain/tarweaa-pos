<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->unique()->nullable()->after('name');
            $table->string('pin', 6)->nullable()->after('password');
            $table->string('phone', 20)->nullable()->after('pin');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
        });

        // 2. Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 3. Permissions
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('display_name', 255);
            $table->string('group', 100)->index();
            $table->timestamps();
        });

        // 4. Role Permissions
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('permission_id')->constrained('permissions');
            $table->primary(['role_id', 'permission_id']);
            $table->index('permission_id');
        });

        // 5. User Roles
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('role_id')->constrained('roles');
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->primary(['user_id', 'role_id']);
            $table->index('role_id');
        });

        // 6. POS Devices
        Schema::create('pos_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('identifier', 100)->unique();
            $table->string('location', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 7. Shifts
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_number', 50)->unique();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->foreignId('opened_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('cash_difference', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 8. Cashier Drawer Sessions
        Schema::create('cashier_drawer_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number', 50)->unique();
            $table->foreignId('cashier_id')->constrained('users');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('pos_device_id')->constrained('pos_devices');
            $table->foreignId('opened_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->decimal('opening_balance', 12, 2)->default(0.00);
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->decimal('expected_balance', 12, 2)->nullable();
            $table->decimal('cash_difference', 12, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 9. Cashier Active Sessions
        Schema::create('cashier_active_sessions', function (Blueprint $table) {
            $table->foreignId('cashier_id')->primary()->constrained('users');
            $table->foreignId('drawer_session_id')->unique()->constrained('cashier_drawer_sessions');
            $table->foreignId('pos_device_id')->constrained('pos_devices');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->timestamp('created_at')->useCurrent();
        });

        // 10. Cash Movements
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drawer_session_id')->constrained('cashier_drawer_sessions');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('cashier_id')->constrained('users');
            $table->enum('type', ['opening', 'sale', 'refund', 'cash_in', 'cash_out', 'closing'])->index();
            $table->enum('direction', ['in', 'out']);
            $table->decimal('amount', 12, 2);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 11. Customers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->index();
            $table->string('phone', 20)->unique();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 12. Orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->enum('type', ['takeaway', 'pickup', 'delivery'])->index();
            $table->enum('status', ['pending', 'confirmed', 'preparing', 'ready', 'dispatched', 'delivered', 'cancelled', 'refunded'])->default('pending')->index();
            $table->enum('source', ['pos', 'talabat', 'jahez', 'hungerstation', 'other'])->default('pos')->index();
            $table->foreignId('cashier_id')->constrained('users');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('drawer_session_id')->constrained('cashier_drawer_sessions');
            $table->foreignId('pos_device_id')->constrained('pos_devices');
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('discount_value', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('delivery_fee', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->enum('payment_status', ['unpaid', 'paid', 'partial', 'refunded'])->default('unpaid')->index();
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('change_amount', 12, 2)->default(0.00);
            $table->decimal('refund_amount', 12, 2)->default(0.00);
            $table->text('refund_reason')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users');
            $table->timestamp('refunded_at')->nullable();
            $table->string('external_order_id', 255)->nullable()->index();
            $table->string('external_order_number', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });

        // 13. Order Payments
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->enum('payment_method', ['cash', 'card', 'online', 'talabat_pay', 'jahez_pay', 'other'])->index();
            $table->decimal('amount', 12, 2);
            $table->string('reference_number', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 16. Menu Categories
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_categories');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 17. Menu Items
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('menu_categories');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('sku', 100)->nullable()->unique();
            $table->string('image', 500)->nullable();
            $table->enum('type', ['simple', 'variable'])->default('simple')->index();
            $table->decimal('base_price', 12, 2)->default(0.00);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('preparation_time')->nullable();
            $table->boolean('track_inventory')->default(false);
            $table->boolean('is_available')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 18. Menu Item Variants
        Schema::create('menu_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items');
            $table->string('name', 255);
            $table->string('sku', 100)->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->boolean('is_available')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 14. Order Items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('menu_item_id')->constrained('menu_items');
            $table->foreignId('menu_item_variant_id')->nullable()->constrained('menu_item_variants');
            $table->string('item_name', 255);
            $table->string('variant_name', 255)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2);
            $table->enum('status', ['pending', 'preparing', 'ready', 'cancelled'])->default('pending')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 19. Modifier Groups
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->enum('selection_type', ['single', 'multiple'])->default('multiple');
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('min_selections')->default(0);
            $table->unsignedTinyInteger('max_selections')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 20. Menu Item Modifier Groups (Pivot)
        Schema::create('menu_item_modifier_groups', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->constrained('menu_items');
            $table->foreignId('modifier_group_id')->constrained('modifier_groups');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['menu_item_id', 'modifier_group_id']);
        });

        // 21. Menu Item Modifiers
        Schema::create('menu_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained('modifier_groups');
            $table->string('name', 255);
            $table->decimal('price', 12, 2)->default(0.00);
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->boolean('is_available')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 15. Order Item Modifiers
        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->index('idx_oim_order_item_id')->constrained('order_items');
            $table->foreignId('menu_item_modifier_id')->constrained('menu_item_modifiers');
            $table->string('modifier_name', 255);
            $table->decimal('price', 12, 2)->default(0.00);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        // 22. Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('contact_person', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->string('payment_terms', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 23. Inventory Items
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('sku', 100)->nullable()->unique();
            $table->string('category', 100)->nullable()->index();
            $table->string('unit', 50);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('current_stock', 10, 3)->default(0.000)->index();
            $table->decimal('minimum_stock', 10, 3)->default(0.000);
            $table->decimal('maximum_stock', 10, 3)->nullable();
            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 24. Inventory Transactions
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->enum('type', ['purchase', 'sale_deduction', 'adjustment', 'waste', 'return', 'transfer_in', 'transfer_out'])->index();
            $table->decimal('quantity', 10, 3);
            $table->decimal('quantity_before', 10, 3);
            $table->decimal('quantity_after', 10, 3);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index('created_at');
        });

        // 25. Purchases
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->enum('status', ['draft', 'ordered', 'received', 'partially_received', 'cancelled'])->default('draft')->index();
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->index();
            $table->string('payment_method', 100)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 26. Purchase Items
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases');
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->string('unit', 50);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('quantity_ordered', 10, 3);
            $table->decimal('quantity_received', 10, 3)->default(0.000);
            $table->decimal('total', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 27. Expense Categories
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 28. Expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 50)->unique();
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->foreignId('shift_id')->nullable()->constrained('shifts');
            $table->foreignId('drawer_session_id')->nullable()->constrained('cashier_drawer_sessions');
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer'])->index();
            $table->string('receipt_number', 100)->nullable();
            $table->date('expense_date')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('menu_item_modifiers');
        Schema::dropIfExists('menu_item_modifier_groups');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('menu_item_variants');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cashier_active_sessions');
        Schema::dropIfExists('cashier_drawer_sessions');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('pos_devices');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['username', 'pin', 'phone', 'is_active', 'created_by', 'updated_by', 'deleted_at']);
        });
    }
};
