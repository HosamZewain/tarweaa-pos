<x-filament-panels::page>
    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير تسوية الأدراج"
            description="مراجعة الجلسات المغلقة، الرصيد المتوقع مقابل الفعلي، وأبرز فروق النقد لمتابعة الانضباط المالي."
            :from="$date_from"
            :to="$date_to"
            :meta="['تركيز على الفروقات النقدية', 'العملة الموحدة: ج.م']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">حدد الفترة المطلوبة لمراجعة جلسات الأدراج المغلقة وفروقات التسوية.</p>
                </div>

                <x-admin.badge tone="warning">تسوية نقدية</x-admin.badge>
            </div>

            <form wire:submit="generateReport">
                {{ $this->form }}

                <div class="admin-filter-card__actions">
                    <x-filament::button type="submit" icon="heroicon-o-funnel">
                        عرض التقرير
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if ($reportData)
            @php
                $difference = $reportData['totals']['total_difference'];
                $differenceTone = $difference < 0 ? 'danger' : ($difference > 0 ? 'warning' : 'success');
            @endphp

            <div class="admin-metric-grid xl:grid-cols-5">
                <x-admin.metric-card
                    title="عدد الجلسات"
                    :value="number_format($reportData['totals']['total_sessions'])"
                    hint="جلسات مغلقة خلال الفترة"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="إجمالي رصيد الفتح"
                    :value="number_format($reportData['totals']['total_opening'], 2) . ' ج.م'"
                    hint="رصيد بداية الجلسات"
                    tone="info"
                />

                <x-admin.metric-card
                    title="إجمالي رصيد الإغلاق"
                    :value="number_format($reportData['totals']['total_closing'], 2) . ' ج.م'"
                    hint="الرصيد الفعلي عند الإغلاق"
                    tone="success"
                />

                <x-admin.metric-card
                    title="إجمالي الفرق"
                    :value="number_format($difference, 2) . ' ج.م'"
                    hint="صافي الفرق بين المتوقع والفعلي"
                    :tone="$differenceTone"
                />

                <x-admin.metric-card
                    title="جلسات بها فروق"
                    :value="number_format($reportData['totals']['sessions_with_diff'])"
                    :hint="$reportData['totals']['sessions_with_diff'] > 0 ? 'تتطلب مراجعة' : 'كل الجلسات متطابقة'"
                    :tone="$reportData['totals']['sessions_with_diff'] > 0 ? 'warning' : 'neutral'"
                />
            </div>

            <x-admin.table-card
                heading="تفاصيل الجلسات المغلقة"
                description="تفصيل لكل جلسة مع مقارنة رصيد الفتح والمتوقع والإغلاق وناتج الفارق."
                :count="$reportData['sessions']->total()"
            >
                @if ($reportData['sessions']->isEmpty())
                    <x-admin.empty-state title="لا توجد جلسات مغلقة في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>رقم الجلسة</th>
                                    <th>الكاشير</th>
                                    <th>الجهاز</th>
                                    <th>رصيد الفتح</th>
                                    <th>الرصيد المتوقع</th>
                                    <th>رصيد الإغلاق</th>
                                    <th>الفرق</th>
                                    <th>تاريخ الإغلاق</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['sessions'] as $session)
                                    @php
                                        $sessionDifference = (float) $session->cash_difference;
                                        $tone = $sessionDifference < 0 ? 'danger' : ($sessionDifference > 0 ? 'warning' : 'success');
                                    @endphp
                                    <tr>
                                        <td class="font-mono font-semibold text-gray-900 dark:text-white">{{ $session->session_number }}</td>
                                        <td>{{ $session->cashier->name ?? '—' }}</td>
                                        <td>{{ $session->posDevice->name ?? '—' }}</td>
                                        <td>{{ number_format($session->opening_balance, 2) }} ج.م</td>
                                        <td>{{ number_format($session->expected_balance, 2) }} ج.م</td>
                                        <td>{{ number_format($session->closing_balance, 2) }} ج.م</td>
                                        <td>
                                            <x-admin.badge :tone="$tone">
                                                {{ number_format($sessionDifference, 2) }} ج.م
                                            </x-admin.badge>
                                        </td>
                                        <td class="text-gray-500 dark:text-gray-400">{{ $session->ended_at ? \App\Support\BusinessTime::formatDateTime($session->ended_at) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($reportData['sessions']->hasPages())
                        <div class="px-5 py-4">
                            {{ $reportData['sessions']->links() }}
                        </div>
                    @endif
                @endif
            </x-admin.table-card>

            @if ($reportData['variance']->count() > 0)
                <x-admin.table-card
                    heading="أكبر الفروق النقدية"
                    description="أعلى الجلسات التي ظهر فيها فرق نقدي لتسهيل المراجعة السريعة."
                    :count="$reportData['variance']->count()"
                >
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الكاشير</th>
                                    <th>الوردية</th>
                                    <th>الرصيد المتوقع</th>
                                    <th>رصيد الإغلاق</th>
                                    <th>الفرق</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['variance'] as $variance)
                                    @php
                                        $varianceDifference = (float) $variance->cash_difference;
                                    @endphp
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $variance->cashier->name ?? '—' }}</td>
                                        <td>#{{ $variance->shift->shift_number ?? '—' }}</td>
                                        <td>{{ number_format($variance->expected_balance, 2) }} ج.م</td>
                                        <td>{{ number_format($variance->closing_balance, 2) }} ج.م</td>
                                        <td>
                                            <x-admin.badge :tone="$varianceDifference < 0 ? 'danger' : 'warning'">
                                                {{ number_format($varianceDifference, 2) }} ج.م
                                            </x-admin.badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin.table-card>
            @endif
        @endif
    </div>
</x-filament-panels::page>
