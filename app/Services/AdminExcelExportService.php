<?php

namespace App\Services;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonSerializable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class AdminExcelExportService
{
    public function downloadFromData(string $filename, array $data): StreamedResponse
    {
        return $this->downloadWorkbook($filename, $this->buildSheetsFromData($data));
    }

    public function downloadWorkbook(string $filename, array $sheets): StreamedResponse
    {
        $tempDirectory = storage_path('app/tmp');
        File::ensureDirectoryExists($tempDirectory);

        $tempPath = $tempDirectory . '/' . Str::uuid() . '.xlsx';

        $this->writeWorkbook($tempPath, $sheets);

        return response()->streamDownload(
            function () use ($tempPath): void {
                $stream = fopen($tempPath, 'rb');

                if ($stream !== false) {
                    fpassthru($stream);
                    fclose($stream);
                }

                @unlink($tempPath);
            },
            $this->normalizeFilename($filename),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    public function writeWorkbook(string $path, array $sheets): void
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $sheetDefinitions = array_values(array_filter($sheets, fn (array $sheet): bool => filled($sheet['name'] ?? null)));

        if ($sheetDefinitions === []) {
            $sheetDefinitions = [
                [
                    'name' => 'Report',
                    'rows' => [['لا توجد بيانات']],
                ],
            ];
        }

        $usedSheetNames = [];

        foreach ($sheetDefinitions as $index => $sheetDefinition) {
            $sheet = $index === 0
                ? $writer->getCurrentSheet()
                : $writer->addNewSheetAndMakeItCurrent();

            $sheet->setName($this->uniqueSheetName((string) ($sheetDefinition['name'] ?? 'Sheet'), $usedSheetNames));

            $rows = $sheetDefinition['rows'] ?? [['لا توجد بيانات']];

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }
        }

        $writer->close();
    }

    public function buildSheetsFromData(array $data): array
    {
        $sheets = [];
        $usedSheetNames = [];

        foreach ($data as $name => $value) {
            $this->appendSheetsFromValue(
                sheets: $sheets,
                sheetName: is_string($name) ? $name : 'Sheet',
                value: $value,
                usedSheetNames: $usedSheetNames,
            );
        }

        return $sheets;
    }

    private function appendSheetsFromValue(array &$sheets, string $sheetName, mixed $value, array &$usedSheetNames): void
    {
        $normalizedValue = $this->normalizeValue($value);

        if ($this->isScalarLike($normalizedValue)) {
            $sheets[] = [
                'name' => $this->uniqueSheetName($sheetName, $usedSheetNames),
                'rows' => [
                    ['القيمة'],
                    [$normalizedValue],
                ],
            ];

            return;
        }

        if (is_array($normalizedValue)) {
            if ($normalizedValue === []) {
                $sheets[] = [
                    'name' => $this->uniqueSheetName($sheetName, $usedSheetNames),
                    'rows' => [['لا توجد بيانات']],
                ];

                return;
            }

            if ($this->isAssociative($normalizedValue)) {
                if ($this->allScalarLike($normalizedValue)) {
                    $rows = [['الحقل', 'القيمة']];

                    foreach ($normalizedValue as $key => $item) {
                        $rows[] = [(string) $key, $item];
                    }

                    $sheets[] = [
                        'name' => $this->uniqueSheetName($sheetName, $usedSheetNames),
                        'rows' => $rows,
                    ];

                    return;
                }

                foreach ($normalizedValue as $childKey => $childValue) {
                    $this->appendSheetsFromValue(
                        sheets: $sheets,
                        sheetName: $sheetName . '_' . (string) $childKey,
                        value: $childValue,
                        usedSheetNames: $usedSheetNames,
                    );
                }

                return;
            }

            if ($this->allScalarLike($normalizedValue)) {
                $rows = [['القيمة']];

                foreach ($normalizedValue as $item) {
                    $rows[] = [$item];
                }

                $sheets[] = [
                    'name' => $this->uniqueSheetName($sheetName, $usedSheetNames),
                    'rows' => $rows,
                ];

                return;
            }

            $rows = $this->tabularRows($normalizedValue);

            $sheets[] = [
                'name' => $this->uniqueSheetName($sheetName, $usedSheetNames),
                'rows' => $rows,
            ];
        }
    }

    private function tabularRows(array $records): array
    {
        $flattenedRecords = [];
        $headers = [];

        foreach ($records as $record) {
            $flattened = $this->flattenToRow($this->normalizeValue($record));
            $flattenedRecords[] = $flattened;

            foreach (array_keys($flattened) as $key) {
                if (!in_array($key, $headers, true)) {
                    $headers[] = $key;
                }
            }
        }

        if ($headers === []) {
            return [['لا توجد بيانات']];
        }

        $rows = [$headers];

        foreach ($flattenedRecords as $record) {
            $rows[] = array_map(fn (string $header): mixed => $record[$header] ?? '', $headers);
        }

        return $rows;
    }

    private function flattenToRow(mixed $value, string $prefix = ''): array
    {
        if ($this->isScalarLike($value)) {
            return [$prefix !== '' ? $prefix : 'value' => $value];
        }

        if (!is_array($value)) {
            return [$prefix !== '' ? $prefix : 'value' => $this->stringifyComplexValue($value)];
        }

        $row = [];

        foreach ($value as $key => $item) {
            $column = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;

            if ($this->isScalarLike($item)) {
                $row[$column] = $item;

                continue;
            }

            if (is_array($item) && $this->isAssociative($item)) {
                $row = array_merge($row, $this->flattenToRow($item, $column));

                continue;
            }

            $row[$column] = $this->stringifyComplexValue($item);
        }

        return $row;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof AbstractPaginator) {
            return $this->normalizeValue($value->items());
        }

        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item): mixed => $this->normalizeValue($item))->all();
        }

        if ($value instanceof Model) {
            return $this->normalizeValue($value->toArray());
        }

        if ($value instanceof Arrayable) {
            return $this->normalizeValue($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalizeValue($value->jsonSerialize());
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return $this->normalizeValue(get_object_vars($value));
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value);
        }

        return $value;
    }

    private function stringifyComplexValue(mixed $value): string
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_object($value)) {
            return json_encode($this->normalizeValue($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }

    private function uniqueSheetName(string $sheetName, array &$usedSheetNames): string
    {
        $base = $this->sanitizeSheetName($sheetName);
        $candidate = $base;
        $suffix = 2;

        while (in_array($candidate, $usedSheetNames, true)) {
            $suffixLabel = "-{$suffix}";
            $candidate = Str::limit($base, 31 - strlen($suffixLabel), '') . $suffixLabel;
            $suffix++;
        }

        $usedSheetNames[] = $candidate;

        return $candidate;
    }

    private function sanitizeSheetName(string $sheetName): string
    {
        $sheetName = trim(str_replace(['\\', '/', '?', '*', ':', '[', ']'], '-', $sheetName));
        $sheetName = preg_replace('/\s+/', ' ', $sheetName) ?: 'Sheet';

        return Str::limit($sheetName !== '' ? $sheetName : 'Sheet', 31, '');
    }

    private function normalizeFilename(string $filename): string
    {
        $filename = trim($filename);

        if (!str_ends_with(Str::lower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        return $filename;
    }

    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function allScalarLike(array $array): bool
    {
        foreach ($array as $item) {
            if (!$this->isScalarLike($item)) {
                return false;
            }
        }

        return true;
    }

    private function isScalarLike(mixed $value): bool
    {
        return $value === null || is_scalar($value);
    }
}
