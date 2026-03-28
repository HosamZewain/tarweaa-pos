<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_order_types', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active')->index();
            $table->softDeletes();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('pos_order_type_id')->nullable()->after('pos_device_id')->constrained('pos_order_types')->nullOnDelete();
            $table->string('order_type_name')->nullable()->after('type');
        });

        if (!DB::table('pos_order_types')->where('is_default', true)->exists()) {
            $defaultId = DB::table('pos_order_types')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');

            if ($defaultId) {
                DB::table('pos_order_types')->where('id', $defaultId)->update(['is_default' => true]);
            }
        }

        DB::table('orders')
            ->whereNull('order_type_name')
            ->update([
                'order_type_name' => DB::raw("
                    CASE type
                        WHEN 'takeaway' THEN 'تيك أواي'
                        WHEN 'pickup' THEN 'استلام من الفرع'
                        WHEN 'delivery' THEN 'توصيل'
                        ELSE type
                    END
                "),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_order_type_id');
            $table->dropColumn('order_type_name');
        });

        Schema::table('pos_order_types', function (Blueprint $table) {
            $table->dropColumn('is_default');
            $table->dropSoftDeletes();
        });
    }
};
