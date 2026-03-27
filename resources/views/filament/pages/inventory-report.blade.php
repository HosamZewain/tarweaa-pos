<x-filament-panels::page>
    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير المخزون"
            description="عرض لحظي لقيمة المخزون الحالية وتوزيعها حسب التصنيف لتسهيل متابعة الرصيد المتاح."
            :meta="['عرض حي بدون فلترة تاريخية', 'العملة الموحدة: ج.م']"
        />

        @if ($valuation)
            <div class="admin-metric-grid md:grid-cols-3">
                <x-admin.metric-card
                    title="إجمالي قيمة المخزون"
                    :value="number_format($valuation['summary']['total_value'], 2) . ' ج.م'"
                    hint="القيمة الحالية التقديرية للمخزون"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="إجمالي الكميات"
                    :value="number_format($valuation['summary']['total_items'], 3)"
                    hint="إجمالي الوحدات المتاحة"
                    tone="info"
                />

                <x-admin.metric-card
                    title="عدد التصنيفات"
                    :value="number_format(count($valuation['breakdown']))"
                    hint="تصنيفات لها رصيد مخزون حالي"
                    tone="success"
                />
            </div>

            <x-admin.table-card
                heading="توزيع المخزون حسب التصنيف"
                description="يوضح قيمة كل تصنيف وحجمه النسبي من إجمالي قيمة المخزون."
                :count="count($valuation['breakdown'])"
            >
                @if (empty($valuation['breakdown']))
                    <x-admin.empty-state title="لا توجد بيانات تفصيلية للمخزون" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>التصنيف</th>
                                    <th>القيمة الإجمالية</th>
                                    <th>عدد الوحدات</th>
                                    <th>المساهمة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($valuation['breakdown'] as $category)
                                    @php
                                        $share = $valuation['summary']['total_value'] > 0
                                            ? round(($category['total_value'] / $valuation['summary']['total_value']) * 100, 1)
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $category['category'] ?? '—' }}</td>
                                        <td>{{ number_format($category['total_value'], 2) }} ج.م</td>
                                        <td>{{ number_format($category['total_items'], 3) }}</td>
                                        <td class="min-w-52">
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ number_format($share, 1) }}%</span>
                                                <div class="admin-progress flex-1" aria-hidden="true">
                                                    <span style="width: {{ $share }}%"></span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>
        @else
            <div class="admin-table-card">
                <x-admin.empty-state
                    title="لا توجد بيانات مخزون متاحة"
                    description="تحقق من وجود مواد مخزنية نشطة أو حركات مخزون مسجلة ثم أعد فتح التقرير."
                />
            </div>
        @endif
    </div>
</x-filament-panels::page>
