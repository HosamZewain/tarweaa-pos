<?php

use App\Support\MenuCsvImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
