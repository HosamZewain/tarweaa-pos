<?php

namespace Tests\Feature;

use App\Services\AdminExcelExportService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminExcelExportServiceTest extends TestCase
{
    public function test_build_sheets_from_nested_data_creates_expected_sheet_shapes(): void
    {
        $service = app(AdminExcelExportService::class);

        $sheets = $service->buildSheetsFromData([
            'summary' => [
                'رقم الطلب' => 'ORD-0001',
                'الإجمالي' => 125.5,
            ],
            'items' => [
                ['الصنف' => 'فلافل', 'الكمية' => 2, 'الإجمالي' => 40],
                ['الصنف' => 'طحينة', 'الكمية' => 1, 'الإجمالي' => 15],
            ],
            'payments' => [
                ['الطريقة' => 'نقدي', 'المبلغ' => 55],
            ],
        ]);

        $this->assertCount(3, $sheets);
        $this->assertSame('summary', $sheets[0]['name']);
        $this->assertSame(['الحقل', 'القيمة'], $sheets[0]['rows'][0]);
        $this->assertSame('items', $sheets[1]['name']);
        $this->assertSame(['الصنف', 'الكمية', 'الإجمالي'], $sheets[1]['rows'][0]);
        $this->assertSame(['فلافل', 2, 40], $sheets[1]['rows'][1]);
        $this->assertSame('payments', $sheets[2]['name']);
    }

    public function test_write_workbook_creates_non_empty_xlsx_file(): void
    {
        $service = app(AdminExcelExportService::class);
        $directory = storage_path('app/testing');
        File::ensureDirectoryExists($directory);

        $path = $directory . '/admin-export-test.xlsx';

        try {
            $service->writeWorkbook($path, [
                [
                    'name' => 'summary',
                    'rows' => [
                        ['الحقل', 'القيمة'],
                        ['إجمالي المبيعات', 1500.75],
                    ],
                ],
                [
                    'name' => 'orders',
                    'rows' => [
                        ['رقم الطلب', 'الإجمالي'],
                        ['ORD-001', 100],
                        ['ORD-002', 200],
                    ],
                ],
            ]);

            $this->assertFileExists($path);
            $this->assertGreaterThan(0, filesize($path) ?: 0);
        } finally {
            @unlink($path);
        }
    }
}
