<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\MenuCsvImporter;
use App\Support\BusinessTime;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('menu:import-csv {path : Absolute path to the CSV file} {--actor-id=} ', function (MenuCsvImporter $importer) {
    $path = (string) $this->argument('path');
    $actorId = $this->option('actor-id');
    $actorId = $actorId !== null ? (int) $actorId : null;

    $summary = $importer->import($path, $actorId);

    $this->info('تم استيراد القائمة بنجاح.');
    $this->table(
        ['المؤشر', 'القيمة'],
        collect($summary)->map(fn ($value, $key) => [$key, $value])->all(),
    );
})->purpose('Import menu categories and items from a CSV file');

Artisan::command('orders:mark-stale-counter-orders-delivered {--force : Apply the update instead of dry-run} {--actor-id= : Optional user id to write into updated_by}', function () {
    $actorId = $this->option('actor-id');
    $actorId = $actorId !== null ? (int) $actorId : null;

    [$businessDayStartUtc,] = BusinessTime::utcRangeForLocalDate(BusinessTime::today());

    $query = Order::query()
        ->where('payment_status', PaymentStatus::Paid->value)
        ->whereIn('status', [
            OrderStatus::Confirmed->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
        ])
        ->where('created_at', '<', $businessDayStartUtc);

    $count = (clone $query)->count();

    $this->info('Stale counter orders cleanup');
    $this->table(
        ['Field', 'Value'],
        [
            ['Business timezone', BusinessTime::timezone()],
            ['Cutoff (UTC)', $businessDayStartUtc->toDateTimeString()],
            ['Eligible orders', (string) $count],
            ['Mode', $this->option('force') ? 'apply' : 'dry-run'],
        ],
    );

    if ($count === 0) {
        $this->warn('No matching orders found.');
        return self::SUCCESS;
    }

    $sample = (clone $query)
        ->with(['shift:id,shift_number,started_at'])
        ->orderBy('created_at')
        ->limit(20)
        ->get([
            'id',
            'order_number',
            'status',
            'payment_status',
            'shift_id',
            'created_at',
            'confirmed_at',
            'ready_at',
        ]);

    $this->table(
        ['ID', 'Order', 'Status', 'Shift', 'Created At', 'Ready At'],
        $sample->map(fn (Order $order) => [
            $order->id,
            $order->order_number,
            $order->status->value,
            $order->shift?->shift_number ?? '—',
            BusinessTime::formatDateTime($order->created_at),
            BusinessTime::formatDateTime($order->ready_at),
        ])->all(),
    );

    if (!$this->option('force')) {
        $this->warn('Dry-run only. Re-run with --force to update these orders.');
        return self::SUCCESS;
    }

    $updated = DB::transaction(function () use ($query, $actorId) {
        $orders = $query->lockForUpdate()->get();

        foreach ($orders as $order) {
            $deliveredAt = $order->ready_at
                ?? $order->confirmed_at
                ?? $order->created_at
                ?? now();

            $payload = [
                'status' => OrderStatus::Delivered->value,
                'delivered_at' => $deliveredAt,
            ];

            if ($actorId !== null) {
                $payload['updated_by'] = $actorId;
            }

            $order->update($payload);
        }

        return $orders->count();
    });

    $this->info("Updated {$updated} orders to delivered.");

    return self::SUCCESS;
})->purpose('Mark paid stale counter-visible orders from before today as delivered');
