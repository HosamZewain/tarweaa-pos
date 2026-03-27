<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_logs', function (Blueprint $table) {
            $table->foreignId('requested_by')
                ->nullable()
                ->after('applied_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('reason')
                ->nullable()
                ->after('previous_discount_amount');
        });

        DB::table('discount_logs')
            ->join('orders', 'orders.id', '=', 'discount_logs.order_id')
            ->whereNull('discount_logs.requested_by')
            ->update([
                'discount_logs.requested_by' => DB::raw('orders.cashier_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('discount_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn('reason');
        });
    }
};
