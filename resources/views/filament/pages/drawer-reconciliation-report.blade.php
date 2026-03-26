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
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-6">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">عدد الجلسات</p>
                    <p class="text-2xl font-bold text-primary-600">{{ number_format($reportData['totals']['total_sessions']) }}</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">إجمالي رصيد الفتح</p>
                    <p class="text-xl font-bold text-info-600">{{ number_format($reportData['totals']['total_opening'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">إجمالي رصيد الإغلاق</p>
                    <p class="text-xl font-bold text-success-600">{{ number_format($reportData['totals']['total_closing'], 2) }} ج.م</p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">إجمالي الفرق</p>
                    @php $diff = $reportData['totals']['total_difference']; @endphp
                    <p class="text-xl font-bold {{ $diff < 0 ? 'text-danger-600' : ($diff > 0 ? 'text-warning-600' : 'text-success-600') }}">
                        {{ number_format($diff, 2) }} ج.م
                    </p>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-gray-500">جلسات بها فروق</p>
                    <p class="text-xl font-bold {{ $reportData['totals']['sessions_with_diff'] > 0 ? 'text-warning-600' : 'text-success-600' }}">
                        {{ number_format($reportData['totals']['sessions_with_diff']) }}
                    </p>
                </div>
            </x-filament::section>
        </div>

        {{-- Reconciliation Table --}}
        <x-filament::section class="mt-6" heading="تفاصيل الجلسات المغلقة">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-right py-2 px-3">رقم الجلسة</th>
                        <th class="text-right py-2 px-3">الكاشير</th>
                        <th class="text-right py-2 px-3">الجهاز</th>
                        <th class="text-right py-2 px-3">رصيد الفتح</th>
                        <th class="text-right py-2 px-3">الرصيد المتوقع</th>
                        <th class="text-right py-2 px-3">رصيد الإغلاق</th>
                        <th class="text-right py-2 px-3">الفرق</th>
                        <th class="text-right py-2 px-3">تاريخ الإغلاق</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData['sessions'] as $session)
                        @php $d = (float) $session->cash_difference; @endphp
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="py-2 px-3 font-mono">{{ $session->session_number }}</td>
                            <td class="py-2 px-3">{{ $session->cashier->name ?? '—' }}</td>
                            <td class="py-2 px-3">{{ $session->posDevice->name ?? '—' }}</td>
                            <td class="py-2 px-3">{{ number_format($session->opening_balance, 2) }} ج.م</td>
                            <td class="py-2 px-3">{{ number_format($session->expected_balance, 2) }} ج.م</td>
                            <td class="py-2 px-3">{{ number_format($session->closing_balance, 2) }} ج.م</td>
                            <td class="py-2 px-3 font-bold {{ $d < 0 ? 'text-danger-600' : ($d > 0 ? 'text-warning-600' : 'text-success-600') }}">
                                {{ number_format($d, 2) }} ج.م
                            </td>
                            <td class="py-2 px-3 text-gray-500">{{ $session->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-gray-400">لا توجد جلسات مغلقة في هذه الفترة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Pagination --}}
            @if($reportData['sessions']->hasPages())
                <div class="mt-4">
                    {{ $reportData['sessions']->links() }}
                </div>
            @endif
        </x-filament::section>

        {{-- Variance Highlight --}}
        @if($reportData['variance']->count() > 0)
            <x-filament::section class="mt-6" heading="أكبر الفروق النقدية">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-right py-2 px-3">الكاشير</th>
                            <th class="text-right py-2 px-3">الوردية</th>
                            <th class="text-right py-2 px-3">الرصيد المتوقع</th>
                            <th class="text-right py-2 px-3">رصيد الإغلاق</th>
                            <th class="text-right py-2 px-3">الفرق</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['variance'] as $v)
                            @php $d = (float) $v->cash_difference; @endphp
                            <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="py-2 px-3">{{ $v->cashier->name ?? '—' }}</td>
                                <td class="py-2 px-3">{{ $v->shift->shift_number ?? '—' }}</td>
                                <td class="py-2 px-3">{{ number_format($v->expected_balance, 2) }} ج.م</td>
                                <td class="py-2 px-3">{{ number_format($v->closing_balance, 2) }} ج.م</td>
                                <td class="py-2 px-3 font-bold {{ $d < 0 ? 'text-danger-600' : 'text-warning-600' }}">
                                    {{ number_format($d, 2) }} ج.م
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
