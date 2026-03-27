<?php

namespace App\Support;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuCsvImporter
{
    public function import(string $path, ?int $actorId = null): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("ملف CSV غير موجود: {$path}");
        }

        $actorId ??= $this->resolveActorId();
        $rows = $this->readRows($path);

        $groupedRows = collect($rows)
            ->groupBy(fn (array $row) => $row['category'] . '||' . $row['item']);

        $summary = [
            'categories_created' => 0,
            'categories_updated' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'variants_created' => 0,
            'duplicates_skipped' => 0,
        ];

        DB::transaction(function () use ($groupedRows, $actorId, &$summary): void {
            $categorySortOrder = 1;

            foreach ($groupedRows->groupBy(fn ($rows, $key) => explode('||', $key)[0]) as $categoryName => $items) {
                [$category, $createdCategory] = $this->upsertCategory($categoryName, $categorySortOrder++, $actorId);
                $summary[$createdCategory ? 'categories_created' : 'categories_updated']++;

                $itemSortOrder = 1;

                foreach ($items as $groupRows) {
                    $normalizedRows = $this->normalizeDuplicateRows($groupRows->all(), $summary);
                    if ($normalizedRows === []) {
                        continue;
                    }

                    $itemName = $normalizedRows[0]['item'];
                    $hasVariants = collect($normalizedRows)->contains(fn (array $row) => $row['size'] !== '');

                    [$menuItem, $createdItem] = $this->upsertMenuItem(
                        category: $category,
                        itemName: $itemName,
                        rows: $normalizedRows,
                        hasVariants: $hasVariants,
                        sortOrder: $itemSortOrder++,
                        actorId: $actorId,
                    );

                    $summary[$createdItem ? 'items_created' : 'items_updated']++;

                    if ($hasVariants) {
                        $summary['variants_created'] += $this->syncVariants($menuItem, $normalizedRows, $actorId);
                    } else {
                        $menuItem->variants()->delete();
                    }
                }
            }
        });

        return $summary;
    }

    protected function readRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException("تعذر فتح الملف: {$path}");
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '\\');
            if (!$header) {
                return [];
            }

            $header = array_map(function ($value) {
                $normalized = trim((string) $value);

                return preg_replace('/^\x{FEFF}/u', '', $normalized) ?? $normalized;
            }, $header);
            $rows = [];

            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($data === [null] || $data === false) {
                    continue;
                }

                $row = array_combine($header, array_pad($data, count($header), null));
                if (!$row) {
                    continue;
                }

                $category = trim((string) ($row['category'] ?? ''));
                $item = trim((string) ($row['item'] ?? ''));
                $size = trim((string) ($row['size'] ?? ''));
                $price = $this->normalizePrice($row['price'] ?? null);

                if ($category === '' || $item === '' || $price === null) {
                    continue;
                }

                $rows[] = [
                    'category' => $category,
                    'item' => $item,
                    'size' => $size,
                    'price' => $price,
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    protected function normalizePrice(mixed $value): ?float
    {
        $clean = trim(str_replace([',', 'ج.م', 'EGP'], '', (string) $value));

        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }

        return round((float) $clean, 2);
    }

    protected function normalizeDuplicateRows(array $rows, array &$summary): array
    {
        $unique = [];

        foreach ($rows as $row) {
            $key = implode('|', [$row['category'], $row['item'], $row['size'], number_format($row['price'], 2, '.', '')]);

            if (isset($unique[$key])) {
                $summary['duplicates_skipped']++;
                continue;
            }

            $unique[$key] = $row;
        }

        return array_values($unique);
    }

    protected function upsertCategory(string $name, int $sortOrder, ?int $actorId): array
    {
        $category = MenuCategory::withTrashed()->firstOrNew(['name' => $name]);
        $created = !$category->exists;

        if ($category->trashed()) {
            $category->restore();
        }

        $category->fill([
            'parent_id' => null,
            'description' => null,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'updated_by' => $actorId,
        ]);

        if (!$category->exists && $actorId !== null) {
            $category->created_by = $actorId;
        }

        $category->save();

        return [$category, $created];
    }

    protected function upsertMenuItem(
        MenuCategory $category,
        string $itemName,
        array $rows,
        bool $hasVariants,
        int $sortOrder,
        ?int $actorId,
    ): array {
        $menuItem = MenuItem::withTrashed()
            ->where('category_id', $category->id)
            ->where('name', $itemName)
            ->first() ?? new MenuItem([
                'category_id' => $category->id,
                'name' => $itemName,
            ]);

        $created = !$menuItem->exists;

        if ($menuItem->exists && $menuItem->trashed()) {
            $menuItem->restore();
        }

        $basePrice = collect($rows)->min('price');

        $menuItem->fill([
            'category_id' => $category->id,
            'name' => $itemName,
            'description' => null,
            'sku' => $this->makeSku($category->name, $itemName),
            'type' => $hasVariants ? 'variable' : 'simple',
            'base_price' => $basePrice,
            'track_inventory' => false,
            'is_available' => true,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'updated_by' => $actorId,
        ]);

        if (!$menuItem->exists && $actorId !== null) {
            $menuItem->created_by = $actorId;
        }

        $menuItem->save();

        return [$menuItem, $created];
    }

    protected function syncVariants(MenuItem $menuItem, array $rows, ?int $actorId): int
    {
        $created = 0;
        $existingVariantIds = [];
        $sortOrder = 1;

        foreach ($this->sortVariantRows($rows) as $row) {
            $variant = $menuItem->variants()
                ->where('name', $row['size'])
                ->first() ?? new MenuItemVariant([
                    'menu_item_id' => $menuItem->id,
                    'name' => $row['size'],
                ]);

            if (!$variant->exists) {
                $created++;
                $variant->created_by = $actorId;
            }

            $variant->fill([
                'menu_item_id' => $menuItem->id,
                'name' => $row['size'],
                'sku' => $this->makeSku($menuItem->name, $row['size']),
                'price' => $row['price'],
                'is_available' => true,
                'sort_order' => $sortOrder++,
                'updated_by' => $actorId,
            ]);

            $variant->save();
            $existingVariantIds[] = $variant->id;
        }

        $menuItem->variants()
            ->whereNotIn('id', $existingVariantIds)
            ->delete();

        return $created;
    }

    protected function sortVariantRows(array $rows): array
    {
        $sizeOrder = ['صغير' => 1, 'وسط' => 2, 'كبير' => 3];

        usort($rows, function (array $a, array $b) use ($sizeOrder) {
            return ($sizeOrder[$a['size']] ?? 99) <=> ($sizeOrder[$b['size']] ?? 99);
        });

        return $rows;
    }

    protected function makeSku(string ...$parts): string
    {
        $raw = collect($parts)
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->implode('-');

        $slug = Str::slug($raw, '-');

        if ($slug !== '') {
            return Str::upper(Str::limit($slug, 50, ''));
        }

        return 'MENU-' . Str::upper(substr(md5($raw), 0, 10));
    }

    protected function resolveActorId(): ?int
    {
        $actorId = DB::table('users')->orderBy('id')->value('id');

        return $actorId !== null ? (int) $actorId : null;
    }
}
