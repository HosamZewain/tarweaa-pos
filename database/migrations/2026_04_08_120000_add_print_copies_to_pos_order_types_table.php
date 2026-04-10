<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_types', function (Blueprint $table): void {
            $table->unsignedTinyInteger('print_copies')->default(1)->after('sort_order');
        });

        DB::table('pos_order_types')
            ->whereNull('print_copies')
            ->update(['print_copies' => 1]);
    }

    public function down(): void
    {
        Schema::table('pos_order_types', function (Blueprint $table): void {
            $table->dropColumn('print_copies');
        });
    }
};
