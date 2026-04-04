<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->json('bill_images')->nullable()->after('notes');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->json('bill_images')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn('bill_images');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn('bill_images');
        });
    }
};
