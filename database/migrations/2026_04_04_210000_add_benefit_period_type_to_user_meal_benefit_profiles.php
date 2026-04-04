<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_meal_benefit_profiles', function (Blueprint $table): void {
            $table->string('benefit_period_type', 20)
                ->default('monthly')
                ->after('free_meal_enabled');
        });

        DB::table('user_meal_benefit_profiles')
            ->whereNull('benefit_period_type')
            ->update(['benefit_period_type' => 'monthly']);
    }

    public function down(): void
    {
        Schema::table('user_meal_benefit_profiles', function (Blueprint $table): void {
            $table->dropColumn('benefit_period_type');
        });
    }
};
