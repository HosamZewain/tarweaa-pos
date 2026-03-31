<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transfers', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('destination_location_id')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('transferred_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->after('transferred_by')->constrained('users')->nullOnDelete();
        });

        DB::table('inventory_transfers')->update([
            'requested_by' => DB::raw('COALESCE(created_by, updated_by)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('inventory_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
            $table->dropConstrainedForeignId('transferred_by');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('requested_by');
        });
    }
};
