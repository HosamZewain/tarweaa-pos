<x-filament-panels::page>
    <form wire:submit="generateReport">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-funnel">
                عرض التقرير
            </x-filament::button>
        </div>
    </form>

    @if($reportData)
        <div class="grid grid-cols-1 md:grid-cols-1 gap-4 mt-6">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">عدد المواد بحركة في الفترة</p>
                    <p class="text-2xl font-bold text-primary-600">{{ number_format($reportData['total_items']) }}</p>
                </div>
            </x-filament::section>
        </div>

        @if($reportData['movements']->count() > 0)
            <x-filament::section class="mt-6" heading="تفاصيل حركة المخزون">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-right py-2 px-3">المادة</th>
                            <th class="text-right py-2 px-3">SKU</th>
                            <th class="text-right py-2 px-3">التصنيف</th>
                            <th class="text-right py-2 px-3">الوحدة</th>
                            <th class="text-right py-2 px-3">الوارد</th>
                            <th class="text-right py-2 px-3">الصادر</th>
                            <th class="text-right py-2 px-3">تكلفة الوارد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['movements'] as $row)
                            @php
                                $item = $row['item'];
                                $movements = $row['movements'];
                                // Sum inbound types: purchase, adjustment_add
                                $inbound = collect($movements)
                                    ->filter(fn ($v, $k) => in_array($k, ['purchase', 'adjustment_add', 'return']))
                                    ->sum(fn ($v) => $v['quantity'] ?? 0);
                                $inboundCost = collect($movements)
                                    ->filter(fn ($v, $k) => in_array($k, ['purchase', 'adjustment_add', 'return']))
                                    ->sum(fn ($v) => $v['cost'] ?? 0);
                                $outbound = collect($movements)
                                    ->filter(fn ($v, $k) => in_array($k, ['sale', 'waste', 'adjustment_sub']))
                                    ->sum(fn ($v) => $v['quantity'] ?? 0);
                            @endphp
                            <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="py-2 px-3 font-medium">{{ $item->name ?? '—' }}</td>
                                <td class="py-2 px-3 font-mono text-gray-500 text-xs">{{ $item->sku ?? '—' }}</td>
                                <td class="py-2 px-3">{{ $item->category ?? '—' }}</td>
                                <td class="py-2 px-3">{{ $item->unit ?? '—' }}</td>
                                <td class="py-2 px-3 text-success-600 font-medium">
                                    {{ $inbound > 0 ? '+' . number_format($inbound, 3) : '—' }}
                                </td>
                                <td class="py-2 px-3 text-danger-600 font-medium">
                                    {{ $outbound > 0 ? '-' . number_format($outbound, 3) : '—' }}
                                </td>
                                <td class="py-2 px-3">
                                    {{ $inboundCost > 0 ? number_format($inboundCost, 2) . ' ج.م' : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @else
            <x-filament::section class="mt-6">
                <p class="text-center text-gray-500 py-4">لا توجد حركات مخزون في هذه الفترة</p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
