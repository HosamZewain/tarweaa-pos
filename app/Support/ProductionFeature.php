<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class ProductionFeature
{
    protected static ?bool $isAvailable = null;
    protected static ?bool $hasProductionLocationFlag = null;

    public static function isAvailable(): bool
    {
        if (static::$isAvailable !== null) {
            return static::$isAvailable;
        }

        if (!Schema::hasTable('inventory_items') || !Schema::hasTable('inventory_locations')) {
            return static::$isAvailable = false;
        }

        return static::$isAvailable =
            Schema::hasColumn('inventory_items', 'item_type')
            && Schema::hasTable('production_recipes')
            && Schema::hasTable('production_recipe_lines')
            && Schema::hasTable('production_batches')
            && Schema::hasTable('production_batch_lines')
            && Schema::hasColumn('production_batches', 'waste_quantity')
            && Schema::hasColumn('production_batches', 'waste_notes')
            && Schema::hasColumn('production_batches', 'approved_by')
            && Schema::hasColumn('production_batches', 'voided_by');
    }

    public static function hasProductionLocationFlag(): bool
    {
        if (static::$hasProductionLocationFlag !== null) {
            return static::$hasProductionLocationFlag;
        }

        if (!Schema::hasTable('inventory_locations')) {
            return static::$hasProductionLocationFlag = false;
        }

        return static::$hasProductionLocationFlag = Schema::hasColumn('inventory_locations', 'is_default_production_location');
    }
}
