<x-filament::section heading="الأصناف الأكثر مبيعاً (اليوم)">
    @if($items->isEmpty())
        <p class="text-center text-sm text-gray-400 py-4">لا توجد مبيعات اليوم بعد</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="text-right py-2 px-3 font-medium text-gray-500">#</th>
                    <th class="text-right py-2 px-3 font-medium text-gray-500">الصنف</th>
                    <th class="text-right py-2 px-3 font-medium text-gray-500">الكمية</th>
                    <th class="text-right py-2 px-3 font-medium text-gray-500">الإيرادات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $item)
                    <tr class="border-b last:border-0 hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="py-2 px-3 text-gray-400">{{ $i + 1 }}</td>
                        <td class="py-2 px-3 font-medium">{{ $item->item_name }}</td>
                        <td class="py-2 px-3">{{ number_format($item->total_qty) }}</td>
                        <td class="py-2 px-3 text-success-600 font-medium">{{ number_format($item->total_rev, 2) }} ج.م</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-filament::section>
