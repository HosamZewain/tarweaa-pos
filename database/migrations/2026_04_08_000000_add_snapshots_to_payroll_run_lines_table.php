<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table): void {
            $table->json('penalties_snapshot')->nullable()->after('advances_count');
            $table->json('advances_snapshot')->nullable()->after('penalties_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table): void {
            $table->dropColumn(['penalties_snapshot', 'advances_snapshot']);
        });
    }
};
