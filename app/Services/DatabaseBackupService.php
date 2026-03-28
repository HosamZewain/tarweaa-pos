<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseBackupService
{
    public const BACKUP_DIRECTORY = 'backups/database';
    public const RESETTABLE_OPERATIONAL_TABLES = [
        'discount_logs',
        'order_item_modifiers',
        'order_items',
        'order_payments',
        'orders',
        'cash_movements',
        'expenses',
        'cashier_active_sessions',
        'cashier_drawer_sessions',
        'shifts',
        'customers',
        'purchase_items',
        'purchases',
        'inventory_transactions',
    ];

    public const PRESERVED_MASTER_TABLES = [
        'users',
        'roles',
        'permissions',
        'role_permissions',
        'user_roles',
        'menu_categories',
        'menu_items',
        'menu_item_variants',
        'modifier_groups',
        'menu_item_modifier_groups',
        'menu_item_modifiers',
        'menu_item_recipe_lines',
        'inventory_items',
        'suppliers',
        'expense_categories',
        'pos_devices',
        'payment_terminals',
        'pos_order_types',
    ];

    public function listBackups(): Collection
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::BACKUP_DIRECTORY))
            ->filter(fn (string $path) => str_ends_with($path, '.sql'))
            ->map(function (string $path) use ($disk): array {
                return [
                    'path' => $path,
                    'name' => basename($path),
                    'size' => $disk->size($path),
                    'last_modified' => Carbon::createFromTimestamp($disk->lastModified($path)),
                ];
            })
            ->sortByDesc('last_modified')
            ->values();
    }

    public function createBackup(?string $label = null): array
    {
        $filename = $this->buildFilename($label);
        $path = self::BACKUP_DIRECTORY . '/' . $filename;
        $absolutePath = Storage::disk('local')->path($path);

        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('تعذر إنشاء ملف النسخة الاحتياطية.');
        }

        try {
            $this->writeBackupContents($handle);
        } finally {
            fclose($handle);
        }

        return [
            'path' => $path,
            'filename' => $filename,
        ];
    }

    public function restoreBackup(string $path, bool $createSafetyBackup = true): array
    {
        $disk = Storage::disk('local');

        if (!$disk->exists($path)) {
            throw new RuntimeException('ملف النسخة الاحتياطية المطلوب غير موجود.');
        }

        $safetyBackup = null;

        if ($createSafetyBackup) {
            $safetyBackup = $this->createBackup('pre-restore');
        }

        $sql = $disk->get($path);

        if (blank($sql)) {
            throw new RuntimeException('ملف النسخة الاحتياطية فارغ أو غير صالح.');
        }

        $this->executeSqlDump($sql);

        return [
            'restored_path' => $path,
            'safety_backup' => $safetyBackup,
        ];
    }

    public function restoreUploadedBackup(string $uploadedPath): array
    {
        return $this->restoreBackup($uploadedPath, true);
    }

    public function getOperationalResetSummary(): array
    {
        return [
            'deleted_tables' => self::RESETTABLE_OPERATIONAL_TABLES,
            'preserved_tables' => self::PRESERVED_MASTER_TABLES,
        ];
    }

    public function resetOperationalData(bool $createSafetyBackup = true): array
    {
        $safetyBackup = null;

        if ($createSafetyBackup) {
            $safetyBackup = $this->createBackup('pre-operational-reset');
        }

        $driver = DB::connection()->getDriverName();

        $this->disableForeignKeyChecks($driver);

        try {
            foreach (self::RESETTABLE_OPERATIONAL_TABLES as $table) {
                if (!$this->tableExists($table)) {
                    continue;
                }

                DB::table($table)->delete();
            }

            $this->resetSqliteSequencesIfNeeded($driver);
        } finally {
            $this->enableForeignKeyChecks($driver);
        }

        return [
            'safety_backup' => $safetyBackup,
            'deleted_tables' => self::RESETTABLE_OPERATIONAL_TABLES,
            'preserved_tables' => self::PRESERVED_MASTER_TABLES,
        ];
    }

    private function buildFilename(?string $label = null): string
    {
        $timestamp = now()->format('Ymd_His');
        $suffix = $label ? '_' . preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($label)) : '';

        return "database_backup_{$timestamp}{$suffix}.sql";
    }

    private function writeBackupContents($handle): void
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        fwrite($handle, "-- Tarweaa POS database backup\n");
        fwrite($handle, "-- Database: {$database}\n");
        fwrite($handle, '-- Generated at: ' . now()->toDateTimeString() . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        foreach ($this->getBaseTables() as $table) {
            $createStatement = $this->getCreateTableStatement($table);

            fwrite($handle, "--\n-- Table structure for `{$table}`\n--\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createStatement . ";\n\n");

            $this->writeTableData($handle, $table);
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    private function writeTableData($handle, string $table): void
    {
        $rows = DB::table($table)->get();

        if ($rows->isEmpty()) {
            return;
        }

        fwrite($handle, "--\n-- Data for `{$table}`\n--\n\n");

        $pdo = DB::connection()->getPdo();
        $batch = [];

        foreach ($rows as $row) {
            $attributes = (array) $row;
            $columns = array_map(fn (string $column) => "`{$column}`", array_keys($attributes));
            $values = array_map(fn ($value) => $this->exportValue($pdo, $value), array_values($attributes));

            $batch[] = '(' . implode(', ', $values) . ')';

            if (count($batch) >= 100) {
                fwrite(
                    $handle,
                    sprintf(
                        "INSERT INTO `%s` (%s) VALUES\n%s;\n",
                        $table,
                        implode(', ', $columns),
                        implode(",\n", $batch),
                    ),
                );

                $batch = [];
            }
        }

        if (!empty($batch)) {
            $attributes = (array) $rows->first();
            $columns = array_map(fn (string $column) => "`{$column}`", array_keys($attributes));

            fwrite(
                $handle,
                sprintf(
                    "INSERT INTO `%s` (%s) VALUES\n%s;\n",
                    $table,
                    implode(', ', $columns),
                    implode(",\n", $batch),
                ),
            );
        }
    }

    private function exportValue(\PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => $pdo->quote((string) $value),
        };
    }

    private function getBaseTables(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("
                SELECT name
                FROM sqlite_master
                WHERE type = 'table'
                  AND name NOT LIKE 'sqlite_%'
                ORDER BY name
            "))->pluck('name')->all();
        }

        $database = DB::connection()->getDatabaseName();
        $key = "Tables_in_{$database}";

        return collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn (object $row) => $row->{$key})
            ->values()
            ->all();
    }

    private function getCreateTableStatement(string $table): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);

            if (!$row?->sql) {
                throw new RuntimeException("تعذر قراءة تعريف الجدول {$table}.");
            }

            return $row->sql;
        }

        $row = DB::selectOne("SHOW CREATE TABLE `{$table}`");
        $data = (array) $row;

        return $data['Create Table'] ?? throw new RuntimeException("تعذر قراءة تعريف الجدول {$table}.");
    }

    private function executeSqlDump(string $sql): void
    {
        $pdo = DB::connection()->getPdo();

        foreach ($this->splitSqlStatements($sql) as $statement) {
            $trimmed = trim($statement);

            if ($trimmed === '') {
                continue;
            }

            $pdo->exec($trimmed);
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : null;
            $prev = $i > 0 ? $sql[$i - 1] : null;

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }

                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }

                continue;
            }

            if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                if ($char === '-' && $next === '-') {
                    $after = $i + 2 < $length ? $sql[$i + 2] : '';

                    if ($after === ' ' || $after === "\t" || $after === "\n" || $after === "\r") {
                        $inLineComment = true;
                        $i++;
                        continue;
                    }
                }

                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === "'" && !$inDoubleQuote && !$inBacktick && $prev !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && !$inBacktick && $prev !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $trimmed = trim($buffer);

                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);

        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    private function tableExists(string $table): bool
    {
        return collect($this->getBaseTables())->contains($table);
    }

    private function disableForeignKeyChecks(string $driver): void
    {
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    private function enableForeignKeyChecks(string $driver): void
    {
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function resetSqliteSequencesIfNeeded(string $driver): void
    {
        if ($driver !== 'sqlite' || !$this->tableExists('sqlite_sequence')) {
            return;
        }

        foreach (self::RESETTABLE_OPERATIONAL_TABLES as $table) {
            DB::table('sqlite_sequence')->where('name', $table)->delete();
        }
    }
}
