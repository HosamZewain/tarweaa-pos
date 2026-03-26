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
        {{-- By Cashier --}}
        <x-filament::section class="mt-6" heading="المبيعات حسب الكاشير">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2 px-3">الكاشير</th>
                        <th class="text-right py-2 px-3">عدد الطلبات</th>
                        <th class="text-right py-2 px-3">الإيرادات الإجمالية</th>
                        <th class="text-right py-2 px-3">المرتجعات</th>
                        <th class="text-right py-2 px-3">صافي الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['byCashier'] as $row)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2 px-3 font-medium">{{ $row->cashier->name ?? '—' }}</td>
                            <td class="py-2 px-3">{{ number_format($row->total_orders) }}</td>
                            <td class="py-2 px-3">{{ number_format($row->gross_revenue, 2) }} ج.م</td>
                            <td class="py-2 px-3 text-danger-600">{{ number_format($row->total_refunds, 2) }} ج.م</td>
                            <td class="py-2 px-3 font-bold text-success-600">{{ number_format($row->net_revenue, 2) }} ج.م</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-400">لا توجد بيانات</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($reportData['byCashier']->count() > 0)
                    <tfoot class="border-t-2">
                        <tr class="font-bold bg-gray-50 dark:bg-gray-800">
                            <td class="py-2 px-3">الإجمالي</td>
                            <td class="py-2 px-3">{{ number_format($reportData['byCashier']->sum('total_orders')) }}</td>
                            <td class="py-2 px-3">{{ number_format($reportData['byCashier']->sum('gross_revenue'), 2) }} ج.م</td>
                            <td class="py-2 px-3">{{ number_format($reportData['byCashier']->sum('total_refunds'), 2) }} ج.م</td>
                            <td class="py-2 px-3">{{ number_format($reportData['byCashier']->sum('net_revenue'), 2) }} ج.م</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </x-filament::section>

        {{-- By Shift --}}
        <x-filament::section class="mt-6" heading="المبيعات حسب الوردية">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2 px-3">الوردية</th>
                        <th class="text-right py-2 px-3">البداية</th>
                        <th class="text-right py-2 px-3">النهاية</th>
                        <th class="text-right py-2 px-3">عدد الطلبات</th>
                        <th class="text-right py-2 px-3">الإيرادات الإجمالية</th>
                        <th class="text-right py-2 px-3">المرتجعات</th>
                        <th class="text-right py-2 px-3">صافي الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['byShift'] as $row)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2 px-3 font-medium">{{ $row->shift->shift_number ?? '—' }}</td>
                            <td class="py-2 px-3 text-gray-500 text-xs">{{ $row->shift?->started_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="py-2 px-3 text-gray-500 text-xs">{{ $row->shift?->ended_at?->format('Y-m-d H:i') ?? 'مفتوحة' }}</td>
                            <td class="py-2 px-3">{{ number_format($row->total_orders) }}</td>
                            <td class="py-2 px-3">{{ number_format($row->gross_revenue, 2) }} ج.م</td>
                            <td class="py-2 px-3 text-danger-600">{{ number_format($row->total_refunds, 2) }} ج.م</td>
                            <td class="py-2 px-3 font-bold text-success-600">{{ number_format($row->net_revenue, 2) }} ج.م</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-gray-400">لا توجد بيانات</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
