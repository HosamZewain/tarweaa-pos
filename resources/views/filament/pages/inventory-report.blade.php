<x-filament-panels::page>
    @if($valuation)
        {{-- Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">إجمالي قيمة المخزون</p>
                    <p class="text-3xl font-bold text-primary-600">{{ number_format($valuation['summary']['total_value'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">إجمالي الكميات</p>
                    <p class="text-3xl font-bold text-info-600">{{ number_format($valuation['summary']['total_items'], 3) }}</p>
                </div>
            </x-filament::section>
        </div>

        {{-- Breakdown by Category --}}
        <x-filament::section class="mt-6" heading="توزيع المخزون حسب التصنيف">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2">التصنيف</th>
                        <th class="text-right py-2">القيمة الإجمالية</th>
                        <th class="text-right py-2">عدد الوحدات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($valuation['breakdown'] as $cat)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2">{{ $cat['category'] ?? '—' }}</td>
                            <td class="py-2">{{ number_format($cat['total_value'], 2) }} ج.م</td>
                            <td class="py-2">{{ number_format($cat['total_items'], 3) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-center text-gray-500">لا توجد بيانات مخزون متاحة.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
