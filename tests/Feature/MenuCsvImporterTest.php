<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\MenuCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MenuCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_simple_and_variable_items_from_csv(): void
    {
        $csvPath = storage_path('framework/testing/menu-import.csv');

        File::ensureDirectoryExists(dirname($csvPath));
        File::put($csvPath, "\xEF\xBB\xBF" . implode("\n", [
            'category,item,size,price',
            'الفول,فول,,' . '8',
            'العلب,فول,صغير,12',
            'العلب,فول,وسط,17',
            'العلب,فول,كبير,25',
            'البيض,بيض ميكس تشيز,,23',
            'البيض,بيض ميكس تشيز,,23',
        ]));

        $summary = app(MenuCsvImporter::class)->import($csvPath);

        $this->assertSame(3, $summary['categories_created']);
        $this->assertSame(3, $summary['items_created']);
        $this->assertSame(3, $summary['variants_created']);
        $this->assertSame(1, $summary['duplicates_skipped']);

        $simple = MenuItem::where('name', 'فول')->whereHas('category', fn ($q) => $q->where('name', 'الفول'))->firstOrFail();
        $this->assertSame('simple', $simple->type);
        $this->assertCount(0, $simple->variants);

        $variable = MenuItem::where('name', 'فول')->whereHas('category', fn ($q) => $q->where('name', 'العلب'))->with('variants')->firstOrFail();
        $this->assertSame('variable', $variable->type);
        $this->assertSame('12.00', $variable->base_price);
        $this->assertSame(['صغير', 'وسط', 'كبير'], $variable->variants->pluck('name')->all());
    }
}
