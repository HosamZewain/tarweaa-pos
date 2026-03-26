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
        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">عدد المصروفات</p>
                    <p class="text-2xl font-bold text-primary-600">{{ number_format($reportData['totals']['total_expenses']) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">الإجمالي الكلي</p>
                    <p class="text-2xl font-bold text-danger-600">{{ number_format($reportData['totals']['total_amount'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">المعتمد</p>
                    <p class="text-2xl font-bold text-success-600">{{ number_format($reportData['totals']['approved_amount'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">بانتظار الاعتماد</p>
                    <p class="text-2xl font-bold text-warning-600">{{ number_format($reportData['totals']['pending_amount'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
        </div>

        {{-- By Category --}}
        @if($reportData['byCategory']->count() > 0)
            <x-filament::section class="mt-6" heading="توزيع المصروفات حسب التصنيف">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-right py-2 px-3">التصنيف</th>
                            <th class="text-right py-2 px-3">عدد المعاملات</th>
                            <th class="text-right py-2 px-3">الإجمالي</th>
                            <th class="text-right py-2 px-3">النسبة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['byCategory'] as $category => $data)
                            <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="py-2 px-3 font-medium">{{ $category }}</td>
                                <td class="py-2 px-3">{{ number_format($data['count']) }}</td>
                                <td class="py-2 px-3">{{ number_format($data['total'], 2) }} ج.م</td>
                                <td class="py-2 px-3 text-gray-500">
                                    @if($reportData['totals']['total_amount'] > 0)
                                        {{ number_format($data['total'] / $reportData['totals']['total_amount'] * 100, 1) }}%
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif

        {{-- Expense List --}}
        <x-filament::section class="mt-6" heading="تفاصيل المصروفات">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2 px-3">رقم المصروف</th>
                        <th class="text-right py-2 px-3">التصنيف</th>
                        <th class="text-right py-2 px-3">الوصف</th>
                        <th class="text-right py-2 px-3">المبلغ</th>
                        <th class="text-right py-2 px-3">طريقة الدفع</th>
                        <th class="text-right py-2 px-3">التاريخ</th>
                        <th class="text-right py-2 px-3">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['expenses'] as $expense)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2 px-3 font-mono">{{ $expense->expense_number }}</td>
                            <td class="py-2 px-3">{{ $expense->category->name ?? '—' }}</td>
                            <td class="py-2 px-3 max-w-xs truncate">{{ $expense->description }}</td>
                            <td class="py-2 px-3 font-bold">{{ number_format($expense->amount, 2) }} ج.م</td>
                            <td class="py-2 px-3">
                                @php $methods = ['cash' => 'نقد', 'bank_transfer' => 'تحويل بنكي', 'credit_card' => 'بطاقة ائتمان']; @endphp
                                {{ $methods[$expense->payment_method] ?? $expense->payment_method }}
                            </td>
                            <td class="py-2 px-3 text-gray-500">{{ $expense->expense_date->format('Y-m-d') }}</td>
                            <td class="py-2 px-3">
                                @if($expense->approved_by)
                                    <span class="text-success-600 text-xs font-medium">✓ معتمد</span>
                                @else
                                    <span class="text-warning-600 text-xs font-medium">⏳ بانتظار</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-gray-400">لا توجد مصروفات في هذه الفترة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
