<x-filament-panels::page>
    @php
        $entries = collect($reportData['entries'] ?? []);
        $summary = $reportData['summary'] ?? [];
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير الجرد وفروقات المخزون"
            description="يعرض نتائج الجرد الفعلية على مستوى المواقع مع الكمية قبل الجرد والكمية المعدودة والفرق المسجل."
            :from="$day ?: $date_from"
            :to="$day ?: $date_to"
            :meta="['يعتمد على حركات الجرد الفعلية', 'لا يغيّر تقارير الحركة الحالية', 'يركز على جرد المواقع المسجل في حركة المخزون']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">راجع الجرد حسب الفترة أو اليوم أو الموقع أو المادة أو المستخدم المنفذ.</p>
                </div>

                <x-admin.badge tone="warning">جرد المخزون</x-admin.badge>
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

        <div class="admin-metric-grid xl:grid-cols-5">
            <x-admin.metric-card
                title="حركات الجرد"
                :value="number_format($summary['counts_count'] ?? 0)"
                hint="عدد نتائج الجرد ضمن الفلاتر"
                tone="primary"
            />
            <x-admin.metric-card
                title="زيادات"
                :value="number_format($summary['increase_count'] ?? 0)"
                hint="جرد رفع الرصيد"
                tone="success"
            />
            <x-admin.metric-card
                title="نواقص"
                :value="number_format($summary['decrease_count'] ?? 0)"
                hint="جرد خفّض الرصيد"
                tone="danger"
            />
            <x-admin.metric-card
                title="صافي الفرق"
                :value="number_format($summary['net_variance'] ?? 0, 3)"
                hint="مجموع الفروقات الموقعة"
                tone="info"
            />
            <x-admin.metric-card
                title="إجمالي الفرق"
                :value="number_format($summary['absolute_variance'] ?? 0, 3)"
                hint="مجموع الفروقات المطلقة"
                tone="warning"
            />
        </div>

        <x-admin.table-card
            heading="تفاصيل الجرد"
            description="سجل تفصيلي لكل عملية جرد مع الكمية قبل الجرد والكمية المعدودة والفرق والمستخدم المنفذ."
            :count="$entries->count()"
        >
            @if ($entries->isEmpty())
                <x-admin.empty-state title="لا توجد نتائج جرد ضمن الفلاتر المحددة" />
            @else
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>تاريخ الجرد</th>
                                <th>اليوم</th>
                                <th>الموقع</th>
                                <th>المادة</th>
                                <th>قبل الجرد</th>
                                <th>الكمية المعدودة</th>
                                <th>الفرق</th>
                                <th>الاتجاه</th>
                                <th>نفذ بواسطة</th>
                                <th>الملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $row)
                                <tr>
                                    <td>{{ $row['counted_at_label'] }}</td>
                                    <td>{{ $row['count_day'] }}</td>
                                    <td>{{ $row['location_name'] }}</td>
                                    <td>
                                        <div class="font-semibold">{{ $row['item_name'] }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['item_sku'] }}</div>
                                    </td>
                                    <td>{{ number_format($row['quantity_before'], 3) }} {{ $row['item_unit'] }}</td>
                                    <td>{{ number_format($row['counted_quantity'], 3) }} {{ $row['item_unit'] }}</td>
                                    <td class="font-semibold {{ $row['variance'] > 0 ? 'text-success-600 dark:text-success-400' : ($row['variance'] < 0 ? 'text-danger-600 dark:text-danger-400' : '') }}">
                                        {{ $row['variance'] > 0 ? '+' : '' }}{{ number_format($row['variance'], 3) }}
                                    </td>
                                    <td>{{ $row['direction_label'] }}</td>
                                    <td>{{ $row['performed_by_name'] }}</td>
                                    <td>{{ $row['notes'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.table-card>
    </div>
</x-filament-panels::page>
