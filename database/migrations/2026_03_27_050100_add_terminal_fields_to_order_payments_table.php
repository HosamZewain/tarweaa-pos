<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreignId('terminal_id')
                ->nullable()
                ->after('amount')
                ->constrained('payment_terminals')
                ->nullOnDelete();
            $table->decimal('fee_amount', 12, 2)
                ->default(0)
                ->after('reference_number');
            $table->decimal('net_settlement_amount', 12, 2)
                ->default(0)
                ->after('fee_amount');
        });

        DB::table('order_payments')->update([
            'net_settlement_amount' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('terminal_id');
            $table->dropColumn(['fee_amount', 'net_settlement_amount']);
        });
    }
};
