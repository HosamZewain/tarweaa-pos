<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $orderRows = DB::table('orders')
            ->leftJoin('discount_logs', function ($join) {
                $join->on('discount_logs.order_id', '=', 'orders.id')
                    ->where('discount_logs.scope', '=', 'order');
            })
            ->whereNull('discount_logs.id')
            ->whereNull('orders.deleted_at')
            ->where('orders.discount_amount', '>', 0)
            ->select([
                'orders.id',
                'orders.discount_type',
                'orders.discount_value',
                'orders.discount_amount',
                'orders.created_at',
            ])
            ->get();

        if ($orderRows->isNotEmpty()) {
            DB::table('discount_logs')->insert(
                $orderRows->map(fn ($row) => [
                    'order_id' => $row->id,
                    'order_item_id' => null,
                    'applied_by' => null,
                    'scope' => 'order',
                    'action' => 'backfilled_existing_order',
                    'discount_type' => $row->discount_type,
                    'discount_value' => $row->discount_value,
                    'discount_amount' => $row->discount_amount,
                    'previous_discount_amount' => null,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $now,
                ])->all()
            );
        }

        $itemRows = DB::table('order_items')
            ->leftJoin('discount_logs', function ($join) {
                $join->on('discount_logs.order_item_id', '=', 'order_items.id')
                    ->where('discount_logs.scope', '=', 'item');
            })
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('discount_logs.id')
            ->whereNull('orders.deleted_at')
            ->where('order_items.discount_amount', '>', 0)
            ->select([
                'order_items.id',
                'order_items.order_id',
                'order_items.discount_amount',
                'order_items.created_at',
            ])
            ->get();

        if ($itemRows->isNotEmpty()) {
            DB::table('discount_logs')->insert(
                $itemRows->map(fn ($row) => [
                    'order_id' => $row->order_id,
                    'order_item_id' => $row->id,
                    'applied_by' => null,
                    'scope' => 'item',
                    'action' => 'backfilled_existing_item',
                    'discount_type' => 'fixed',
                    'discount_value' => $row->discount_amount,
                    'discount_amount' => $row->discount_amount,
                    'previous_discount_amount' => null,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $now,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        DB::table('discount_logs')
            ->whereIn('action', ['backfilled_existing_order', 'backfilled_existing_item'])
            ->delete();
    }
};
