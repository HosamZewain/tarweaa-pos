<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('show_in_employee_resource')
                ->default(false)
                ->after('is_active');
        });

        DB::table('roles')
            ->whereIn('name', ['employee', 'cashier', 'kitchen', 'counter'])
            ->update(['show_in_employee_resource' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('show_in_employee_resource');
        });
    }
};
