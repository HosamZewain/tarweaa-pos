<x-filament-panels::page>
    @php
        $summary = $reportData['summary'] ?? [];
        $items = $reportData['items'] ?? collect();
        $selectedItem = $reportData['selectedItem'] ?? null;
        $selectedSummary = $selectedItem['summary'] ?? null;
        $selectedDaily = collect($selectedItem['daily'] ?? []);
        $selectedVariants = collect($selectedItem['variants'] ?? []);
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير الأصناف"
            description="عرض مبيعات كل الأصناف خلال الفترة المحددة، مع إمكانية التركيز على صنف واحد ومراجعة إحصاءاته التفصيلية."
            :from="$date_from"
            :to="$date_to"
            :meta="['يعتمد على الطلبات المدفوعة فقط', 'يستبعد الطلبات الملغاة', 'يدعم تحليل صنف محدد']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">حدد الفترة، ويمكنك اختيار صنف معين لعرض تفاصيله اليومية وإحصاءاته خلال نفس الفترة.</p>
                </div>

                <x-admin.badge tone="primary">الأصناف والمبيعات</x-admin.badge>
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

        <div class="admin-metric-grid">
            <x-admin.metric-card
                title="الأصناف المباعة"
                :value="number_format($summary['distinct_items_count'] ?? 0)"
                hint="عدد الأصناف التي سجلت مبيعات"
                tone="primary"
            />
            <x-admin.metric-card
                title="إجمالي الكمية"
                :value="number_format($summary['total_quantity'] ?? 0, 2)"
                hint="مجموع كميات الأصناف المباعة"
                tone="success"
            />
            <x-admin.metric-card
                title="إجمالي المبيعات"
                :value="number_format($summary['gross_revenue'] ?? 0, 2) . ' ج.م'"
                hint="إجمالي إيراد الأصناف"
                tone="info"
            />
            <x-admin.metric-card
                title="إجمالي التكلفة"
                :value="number_format($summary['total_cost'] ?? 0, 2) . ' ج.م'"
                hint="التكلفة التاريخية المسجلة على السطور"
                tone="warning"
            />
            <x-admin.metric-card
                title="مجمل الربح"
                :value="number_format($summary['gross_profit'] ?? 0, 2) . ' ج.م'"
                hint="المبيعات ناقص التكلفة"
                tone="success"
            />
        </div>

        @if ($selectedSummary)
            <div class="admin-section-stack">
                <x-admin.table-card
                    :heading="'إحصاءات الصنف: ' . ($selectedSummary['item_name'] ?? '—')"
                    description="ملخص تفصيلي للصنف المختار خلال الفترة الحالية."
                    :count="$selectedSummary['orders_count'] ?? 0"
                >
                    <div class="admin-metric-grid xl:grid-cols-6">
                        <x-admin.metric-card
                            title="الكمية المباعة"
                            :value="number_format($selectedSummary['total_quantity'] ?? 0, 2)"
                            hint="إجمالي كميات الصنف"
                            tone="primary"
                        />
                        <x-admin.metric-card
                            title="إجمالي المبيعات"
                            :value="number_format($selectedSummary['total_revenue'] ?? 0, 2) . ' ج.م'"
                            hint="إيراد الصنف"
                            tone="success"
                        />
                        <x-admin.metric-card
                            title="مجمل الربح"
                            :value="number_format($selectedSummary['gross_profit'] ?? 0, 2) . ' ج.م'"
                            hint="بعد خصم التكلفة"
                            tone="info"
                        />
                        <x-admin.metric-card
                            title="عدد الطلبات"
                            :value="number_format($selectedSummary['orders_count'] ?? 0)"
                            hint="طلبات احتوت الصنف"
                            tone="warning"
                        />
                        <x-admin.metric-card
                            title="متوسط سعر الوحدة"
                            :value="number_format($selectedSummary['average_unit_price'] ?? 0, 2) . ' ج.م'"
                            hint="المبيعات ÷ الكمية"
                            tone="neutral"
                        />
                        <x-admin.metric-card
                            title="أيام البيع"
                            :value="number_format($selectedSummary['days_sold_count'] ?? 0)"
                            hint="عدد الأيام التي بيع فيها الصنف"
                            tone="danger"
                        />
                    </div>

                    <div class="mt-6 grid gap-4 xl:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="grid gap-3 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">الفئة</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedSummary['category_name'] ?: '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">أول بيع</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedSummary['first_sold_at'] ?: '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">آخر بيع</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedSummary['last_sold_at'] ?: '—' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="grid gap-3 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">إجمالي التكلفة</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($selectedSummary['total_cost'] ?? 0, 2) }} ج.م</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-gray-500 dark:text-gray-400">متوسط إيراد الطلب</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($selectedSummary['average_order_revenue'] ?? 0, 2) }} ج.م</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-admin.table-card>

                <div class="grid gap-6 xl:grid-cols-2">
                    <x-admin.table-card
                        heading="المبيعات اليومية للصنف"
                        description="توزيع أداء الصنف المختار يوميًا."
                        :count="$selectedDaily->count()"
                    >
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>عدد الطلبات</th>
                                        <th>الكمية</th>
                                        <th>المبيعات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($selectedDaily as $day)
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $day['date'] }}</td>
                                            <td>{{ number_format($day['orders_count']) }}</td>
                                            <td>{{ number_format($day['total_quantity'], 2) }}</td>
                                            <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($day['total_revenue'], 2) }} ج.م</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4">لا توجد مبيعات للصنف في هذه الفترة</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-admin.table-card>

                    <x-admin.table-card
                        heading="تفصيل المتغيرات"
                        description="توزيع المبيعات حسب المتغير أو النسخة إن وجدت."
                        :count="$selectedVariants->count()"
                    >
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>المتغير</th>
                                        <th>عدد الطلبات</th>
                                        <th>الكمية</th>
                                        <th>المبيعات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($selectedVariants as $variant)
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $variant['variant_name'] }}</td>
                                            <td>{{ number_format($variant['orders_count']) }}</td>
                                            <td>{{ number_format($variant['total_quantity'], 2) }}</td>
                                            <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($variant['total_revenue'], 2) }} ج.م</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4">لا توجد متغيرات مباعة في هذه الفترة</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-admin.table-card>
                </div>
            </div>
        @endif

        <x-admin.table-card
            heading="مبيعات كل الأصناف"
            description="جميع الأصناف التي سجلت مبيعات خلال الفترة، مرتبة حسب إجمالي المبيعات."
            :count="$items->count()"
        >
            @if ($items->isEmpty())
                <x-admin.empty-state title="لا توجد مبيعات أصناف في هذه الفترة" />
            @else
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الصنف</th>
                                <th>الفئة</th>
                                <th>عدد الطلبات</th>
                                <th>الكمية</th>
                                <th>متوسط سعر الوحدة</th>
                                <th>المبيعات</th>
                                <th>التكلفة</th>
                                <th>مجمل الربح</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $index => $item)
                                <tr>
                                    <td><x-admin.badge tone="neutral">{{ $index + 1 }}</x-admin.badge></td>
                                    <td class="font-semibold text-gray-900 dark:text-white">{{ $item['item_name'] }}</td>
                                    <td>{{ $item['category_name'] ?: '—' }}</td>
                                    <td>{{ number_format($item['orders_count']) }}</td>
                                    <td>{{ number_format($item['total_quantity'], 2) }}</td>
                                    <td>{{ number_format($item['average_unit_price'], 2) }} ج.م</td>
                                    <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($item['total_revenue'], 2) }} ج.م</td>
                                    <td>{{ number_format($item['total_cost'], 2) }} ج.م</td>
                                    <td class="font-semibold">{{ number_format($item['gross_profit'], 2) }} ج.م</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.table-card>
    </div>
</x-filament-panels::page>
