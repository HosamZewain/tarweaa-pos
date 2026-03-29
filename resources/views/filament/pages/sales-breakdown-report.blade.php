<x-filament-panels::page>
    @php
        $cashierRows = $reportData['byCashier'] ?? collect();
        $shiftRows = $reportData['byShift'] ?? collect();
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            title="تفصيل المبيعات"
            description="تحليل المبيعات حسب الكاشير والوردية لمقارنة الأداء التشغيلي خلال الفترة المحددة."
            :from="$date_from"
            :to="$date_to"
            :meta="['مقارنة بين الكاشير والورديات', 'عرض ملائم للمراجعة التشغيلية']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">اختر الفترة الزمنية لمقارنة أداء الكاشير والورديات بشكل أوضح.</p>
                </div>

                <x-admin.badge tone="info">تفصيل تشغيلي</x-admin.badge>
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
            <div class="admin-metric-grid">
                <x-admin.metric-card
                    title="إجمالي الطلبات"
                    :value="number_format($cashierRows->sum('total_orders'))"
                    hint="من مجموع الكاشير في الفترة"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="الإيرادات الإجمالية"
                    :value="number_format($cashierRows->sum('gross_revenue'), 2) . ' ج.م'"
                    hint="قبل احتساب المرتجعات"
                    tone="success"
                />

                <x-admin.metric-card
                    title="صافي الإيرادات"
                    :value="number_format($cashierRows->sum('net_revenue'), 2) . ' ج.م'"
                    hint="بعد احتساب المرتجعات"
                    tone="info"
                />

                <x-admin.metric-card
                    title="عدد الورديات"
                    :value="number_format($shiftRows->count())"
                    hint="الورديات التي لديها نشاط ضمن الفترة"
                    tone="warning"
                />
            </div>

            <x-admin.table-card
                heading="المبيعات حسب الكاشير"
                description="يسهّل هذا العرض مقارنة حجم الطلبات وصافي المبيعات بين الكاشير."
                :count="$cashierRows->count()"
            >
                @if ($cashierRows->isEmpty())
                    <x-admin.empty-state title="لا توجد بيانات كاشير في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الكاشير</th>
                                    <th>عدد الطلبات</th>
                                    <th>الإيرادات الإجمالية</th>
                                    <th>المرتجعات</th>
                                    <th>صافي الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cashierRows as $row)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $row->cashier->name ?? '—' }}</td>
                                        <td>{{ number_format($row->total_orders) }}</td>
                                        <td>{{ number_format($row->gross_revenue, 2) }} ج.م</td>
                                        <td class="font-semibold text-danger-600 dark:text-danger-400">{{ number_format($row->total_refunds, 2) }} ج.م</td>
                                        <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($row->net_revenue, 2) }} ج.م</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>الإجمالي</td>
                                    <td>{{ number_format($cashierRows->sum('total_orders')) }}</td>
                                    <td>{{ number_format($cashierRows->sum('gross_revenue'), 2) }} ج.م</td>
                                    <td>{{ number_format($cashierRows->sum('total_refunds'), 2) }} ج.م</td>
                                    <td>{{ number_format($cashierRows->sum('net_revenue'), 2) }} ج.م</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>

            <x-admin.table-card
                heading="المبيعات حسب الوردية"
                description="مقارنة تفصيلية بين الورديات مع توقيت الفتح والإغلاق وصافي الأداء."
                :count="$shiftRows->count()"
            >
                @if ($shiftRows->isEmpty())
                    <x-admin.empty-state title="لا توجد بيانات ورديات في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الوردية</th>
                                    <th>الحالة</th>
                                    <th>البداية</th>
                                    <th>النهاية</th>
                                    <th>عدد الطلبات</th>
                                    <th>الإيرادات الإجمالية</th>
                                    <th>المرتجعات</th>
                                    <th>صافي الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shiftRows as $row)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">#{{ $row->shift->shift_number ?? '—' }}</td>
                                        <td>
                                            <x-admin.badge :tone="$row->shift?->ended_at ? 'neutral' : 'success'">
                                                {{ $row->shift?->ended_at ? 'مغلقة' : 'مفتوحة' }}
                                            </x-admin.badge>
                                        </td>
                                        <td class="text-gray-500 dark:text-gray-400">{{ $row->shift?->started_at ? \App\Support\BusinessTime::formatDateTime($row->shift->started_at) : '—' }}</td>
                                        <td class="text-gray-500 dark:text-gray-400">{{ $row->shift?->ended_at ? \App\Support\BusinessTime::formatDateTime($row->shift->ended_at) : 'مفتوحة' }}</td>
                                        <td>{{ number_format($row->total_orders) }}</td>
                                        <td>{{ number_format($row->gross_revenue, 2) }} ج.م</td>
                                        <td class="font-semibold text-danger-600 dark:text-danger-400">{{ number_format($row->total_refunds, 2) }} ج.م</td>
                                        <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($row->net_revenue, 2) }} ج.م</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>
        @endif
    </div>
</x-filament-panels::page>
