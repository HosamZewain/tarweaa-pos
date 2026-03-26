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
        {{-- Summary Totals --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">إجمالي الطلبات</p>
                    <p class="text-2xl font-bold text-primary-600">{{ number_format($reportData['dailySales']['totals']['total_orders']) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">الإيرادات الإجمالية</p>
                    <p class="text-2xl font-bold text-success-600">{{ number_format($reportData['dailySales']['totals']['gross_revenue'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">إجمالي الخصومات</p>
                    <p class="text-2xl font-bold text-warning-600">{{ number_format($reportData['dailySales']['totals']['total_discounts'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">صافي الإيرادات</p>
                    <p class="text-2xl font-bold text-info-600">{{ number_format($reportData['dailySales']['totals']['net_revenue'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
        </div>

        {{-- Daily Sales Table --}}
        <x-filament::section class="mt-6" heading="المبيعات اليومية">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2 px-3">التاريخ</th>
                        <th class="text-right py-2 px-3">الطلبات</th>
                        <th class="text-right py-2 px-3">الإيرادات</th>
                        <th class="text-right py-2 px-3">الخصومات</th>
                        <th class="text-right py-2 px-3">الضريبة</th>
                        <th class="text-right py-2 px-3">المرتجعات</th>
                        <th class="text-right py-2 px-3">الصافي</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['dailySales']['daily'] as $day)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2 px-3">{{ $day->date }}</td>
                            <td class="py-2 px-3">{{ number_format($day->total_orders) }}</td>
                            <td class="py-2 px-3">{{ number_format($day->gross_revenue, 2) }} ج.م</td>
                            <td class="py-2 px-3 text-warning-600">{{ number_format($day->total_discounts, 2) }} ج.م</td>
                            <td class="py-2 px-3">{{ number_format($day->total_tax, 2) }} ج.م</td>
                            <td class="py-2 px-3 text-danger-600">{{ number_format($day->total_refunds, 2) }} ج.م</td>
                            <td class="py-2 px-3 font-bold text-success-600">{{ number_format($day->net_revenue, 2) }} ج.م</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-400">لا توجد بيانات في هذه الفترة</td></tr>
                    @endforelse
                </tbody>
                @if($reportData['dailySales']['daily']->count() > 0)
                <tfoot class="border-t-2">
                    <tr class="font-bold bg-gray-50 dark:bg-gray-800">
                        <td class="py-2 px-3">المجموع</td>
                        <td class="py-2 px-3">{{ number_format($reportData['dailySales']['totals']['total_orders']) }}</td>
                        <td class="py-2 px-3">{{ number_format($reportData['dailySales']['totals']['gross_revenue'], 2) }} ج.م</td>
                        <td class="py-2 px-3">{{ number_format($reportData['dailySales']['totals']['total_discounts'], 2) }} ج.م</td>
                        <td class="py-2 px-3">{{ number_format($reportData['dailySales']['totals']['total_tax'], 2) }} ج.م</td>
                        <td class="py-2 px-3">{{ number_format($reportData['dailySales']['totals']['total_refunds'], 2) }} ج.م</td>
                        <td class="py-2 px-3">{{ number_format($reportData['dailySales']['totals']['net_revenue'], 2) }} ج.م</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </x-filament::section>

        {{-- Top Items --}}
        <x-filament::section class="mt-6" heading="الأصناف الأكثر مبيعاً">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2 px-3">#</th>
                        <th class="text-right py-2 px-3">الصنف</th>
                        <th class="text-right py-2 px-3">الكمية</th>
                        <th class="text-right py-2 px-3">الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['salesByItem'] as $i => $item)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2 px-3 text-gray-400">{{ $i + 1 }}</td>
                            <td class="py-2 px-3 font-medium">{{ $item->item_name }}</td>
                            <td class="py-2 px-3">{{ number_format($item->total_quantity) }}</td>
                            <td class="py-2 px-3">{{ number_format($item->net_revenue, 2) }} ج.م</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">لا توجد بيانات</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        {{-- By Category --}}
        <x-filament::section class="mt-6" heading="المبيعات حسب الفئة">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2 px-3">الفئة</th>
                        <th class="text-right py-2 px-3">الكمية</th>
                        <th class="text-right py-2 px-3">الإيرادات</th>
                        <th class="text-right py-2 px-3">النسبة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['salesByCategory'] as $cat)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2 px-3 font-medium">{{ $cat->category_name }}</td>
                            <td class="py-2 px-3">{{ number_format($cat->total_quantity) }}</td>
                            <td class="py-2 px-3">{{ number_format($cat->net_revenue, 2) }} ج.م</td>
                            <td class="py-2 px-3 text-gray-500">
                                @if($reportData['dailySales']['totals']['gross_revenue'] > 0)
                                    {{ number_format($cat->net_revenue / $reportData['dailySales']['totals']['gross_revenue'] * 100, 1) }}%
                                @else —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">لا توجد بيانات</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        {{-- By Payment Method --}}
        <x-filament::section class="mt-6" heading="المبيعات حسب طريقة الدفع">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2 px-3">طريقة الدفع</th>
                        <th class="text-right py-2 px-3">عدد المعاملات</th>
                        <th class="text-right py-2 px-3">المبلغ الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['salesByPayment'] as $pay)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2 px-3">{{ $pay->payment_method }}</td>
                            <td class="py-2 px-3">{{ number_format($pay->transaction_count) }}</td>
                            <td class="py-2 px-3 font-medium">{{ number_format($pay->total_amount, 2) }} ج.م</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-gray-400">لا توجد بيانات</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
