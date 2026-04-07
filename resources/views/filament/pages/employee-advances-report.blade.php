<x-filament-panels::page>
    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير سلف الموظفين"
            description="متابعة السلف المسجلة للموظفين خلال فترة محددة مع تجميع حسب الموظف وتفصيل كامل للحركات."
            :from="$date_from"
            :to="$date_to"
            :meta="['تقارير HR / محاسبة', 'العملة الموحدة: ج.م']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">اختر الفترة الزمنية، ويمكن حصر النتائج على موظف محدد عند الحاجة.</p>
                </div>

                <x-admin.badge tone="warning">سلف الموظفين</x-admin.badge>
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
                    title="إجمالي السلف"
                    :value="number_format($reportData['totals']['total_advances'])"
                    hint="عدد الحركات المسجلة"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="إجمالي المبالغ"
                    :value="number_format($reportData['totals']['total_amount'], 2) . ' ج.م'"
                    hint="إجمالي السلف في الفترة"
                    tone="warning"
                />

                <x-admin.metric-card
                    title="السلف النشطة"
                    :value="number_format($reportData['totals']['active_amount'], 2) . ' ج.م'"
                    hint="سلف ما زالت محسوبة"
                    tone="success"
                />

                <x-admin.metric-card
                    title="السلف الملغاة"
                    :value="number_format($reportData['totals']['cancelled_amount'], 2) . ' ج.م'"
                    hint="سلف تم إلغاؤها"
                    tone="danger"
                />
            </div>

            <x-admin.table-card
                heading="تجميع حسب الموظف"
                description="إجمالي السلف لكل موظف خلال الفترة المحددة."
                :count="$reportData['byEmployee']->count()"
            >
                @if ($reportData['byEmployee']->isEmpty())
                    <x-admin.empty-state title="لا توجد بيانات مجمعة في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الموظف</th>
                                    <th>المسمى الوظيفي</th>
                                    <th>عدد السلف</th>
                                    <th>إجمالي المبلغ</th>
                                    <th>النشطة</th>
                                    <th>الملغاة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['byEmployee'] as $row)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['job_title'] ?: '—' }}</td>
                                        <td>{{ number_format($row['total_advances']) }}</td>
                                        <td>{{ number_format($row['total_amount'], 2) }} ج.م</td>
                                        <td>{{ number_format($row['active_amount'], 2) }} ج.م</td>
                                        <td>{{ number_format($row['cancelled_amount'], 2) }} ج.م</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>

            <x-admin.table-card
                heading="تفاصيل السلف"
                description="سجل كامل للسلف مع الحالة والملاحظات ومسار الإلغاء إن وجد."
                :count="$reportData['advances']->count()"
            >
                @if ($reportData['advances']->isEmpty())
                    <x-admin.empty-state title="لا توجد سلف خلال الفترة المحددة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الموظف</th>
                                    <th>التاريخ</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>تمت بواسطة</th>
                                    <th>ألغيت بواسطة</th>
                                    <th>ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['advances'] as $advance)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">
                                            {{ $advance->employee?->employeeProfile?->full_name ?: $advance->employee?->name ?: '—' }}
                                        </td>
                                        <td>{{ $advance->advance_date?->format('Y-m-d') ?: '—' }}</td>
                                        <td class="font-bold">{{ number_format((float) $advance->amount, 2) }} ج.م</td>
                                        <td>
                                            @if ($advance->status === 'cancelled')
                                                <x-admin.badge tone="danger">ملغاة</x-admin.badge>
                                            @else
                                                <x-admin.badge tone="success">نشطة</x-admin.badge>
                                            @endif
                                        </td>
                                        <td>{{ $advance->creator?->name ?: '—' }}</td>
                                        <td>{{ $advance->canceller?->name ?: '—' }}</td>
                                        <td>
                                            <div class="max-w-xs truncate" title="{{ $advance->notes ?: $advance->cancellation_reason }}">
                                                {{ $advance->notes ?: ($advance->cancellation_reason ?: '—') }}
                                            </div>
                                        </td>
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
