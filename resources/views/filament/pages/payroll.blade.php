<x-filament-panels::page>
    <div class="admin-page-shell">
        <x-admin.report-header
            title="Payroll"
            description="توليد مسير رواتب شهري ثابت يشمل الراتب الأساسي مطروحًا منه جزاءات الشهر والسلف غير المسددة."
            :from="$reportData['run']['period_start'] ?? null"
            :to="$reportData['run']['period_end'] ?? null"
            :meta="[
                'HR / Payroll',
                'Snapshot شهري ثابت',
                'العملة الموحدة: ج.م',
            ]"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">إعداد المسير</h3>
                    <p class="admin-filter-card__description">اختر الشهر المطلوب. إذا كان هناك مسير محفوظ لهذا الشهر فسيتم عرضه، وإلا ستظهر معاينة قابلة للتوليد.</p>
                </div>

                @if (($reportData['run']['status'] ?? null) === 'approved')
                    <x-admin.badge tone="success">معتمد</x-admin.badge>
                @elseif (($reportData['run']['status'] ?? null) === 'draft')
                    <x-admin.badge tone="warning">مسير محفوظ - Draft</x-admin.badge>
                @else
                    <x-admin.badge tone="info">معاينة</x-admin.badge>
                @endif
            </div>

            <form wire:submit="loadPayroll">
                {{ $this->form }}

                <div class="admin-filter-card__actions">
                    <x-filament::button type="submit" icon="heroicon-o-funnel">
                        تحميل المسير
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if ($reportData)
            <div class="admin-metric-grid">
                <x-admin.metric-card
                    title="عدد الموظفين"
                    :value="number_format($reportData['summary']['employees_count'])"
                    hint="الموظفون المدرجون في هذا المسير"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="إجمالي الرواتب الأساسية"
                    :value="number_format($reportData['summary']['total_base_salary'], 2) . ' ج.م'"
                    hint="قبل الخصومات"
                    tone="info"
                />

                <x-admin.metric-card
                    title="إجمالي الجزاءات"
                    :value="number_format($reportData['summary']['total_penalties'], 2) . ' ج.م'"
                    hint="خصومات الجزاءات في الشهر"
                    tone="danger"
                />

                <x-admin.metric-card
                    title="إجمالي السلف"
                    :value="number_format($reportData['summary']['total_advances'], 2) . ' ج.م'"
                    hint="السلف المخصومة في هذا المسير"
                    tone="warning"
                />

                <x-admin.metric-card
                    title="صافي الرواتب"
                    :value="number_format($reportData['summary']['total_net_salary'], 2) . ' ج.م'"
                    hint="إجمالي المستحق بعد الخصومات"
                    tone="success"
                />
            </div>

            <x-admin.table-card
                heading="مسير الرواتب الشهري"
                description="سطر لكل موظف يوضح الراتب الأساسي والخصومات وصافي الاستحقاق."
                :count="count($reportData['lines'])"
            >
                @if (empty($reportData['lines']))
                    <x-admin.empty-state title="لا يوجد موظفون برواتب سارية في هذا الشهر" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الموظف</th>
                                    <th>المسمى الوظيفي</th>
                                    <th>الراتب الأساسي</th>
                                    <th>الجزاءات</th>
                                    <th>السلف</th>
                                    <th>الصافي</th>
                                    <th>عدد الجزاءات</th>
                                    <th>عدد السلف</th>
                                    <th>ساري من</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['lines'] as $line)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $line['employee_name'] }}</td>
                                        <td>{{ $line['job_title'] ?: '—' }}</td>
                                        <td>{{ number_format($line['base_salary'], 2) }} ج.م</td>
                                        <td>{{ number_format($line['penalties_total'], 2) }} ج.م</td>
                                        <td>{{ number_format($line['advances_total'], 2) }} ج.م</td>
                                        <td class="font-bold">{{ number_format($line['net_salary'], 2) }} ج.م</td>
                                        <td>{{ number_format($line['penalties_count']) }}</td>
                                        <td>{{ number_format($line['advances_count']) }}</td>
                                        <td>{{ $line['salary_effective_from'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>

            <div class="admin-two-column-grid">
                <x-admin.table-card
                    heading="الجزاءات المخصومة"
                    description="جميع الجزاءات الداخلة في هذا المسير."
                    :count="count($reportData['penalties'])"
                >
                    @if (empty($reportData['penalties']))
                        <x-admin.empty-state title="لا توجد جزاءات لهذا الشهر" />
                    @else
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>التاريخ</th>
                                        <th>السبب</th>
                                        <th>القيمة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData['penalties'] as $penalty)
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $penalty['employee_name'] }}</td>
                                            <td>{{ $penalty['penalty_date'] ?: '—' }}</td>
                                            <td>{{ $penalty['reason'] }}</td>
                                            <td>{{ number_format($penalty['amount'], 2) }} ج.م</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>

                <x-admin.table-card
                    heading="السلف المخصومة"
                    description="السلف غير المسددة التي تم تحميلها على هذا الشهر."
                    :count="count($reportData['advances'])"
                >
                    @if (empty($reportData['advances']))
                        <x-admin.empty-state title="لا توجد سلف مخصومة في هذا الشهر" />
                    @else
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>تاريخ السلفة</th>
                                        <th>قيمة السلفة</th>
                                        <th>المخصوم هذا الشهر</th>
                                        <th>ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData['advances'] as $advance)
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $advance['employee_name'] }}</td>
                                            <td>{{ $advance['advance_date'] ?: '—' }}</td>
                                            <td>{{ number_format($advance['original_amount'], 2) }} ج.م</td>
                                            <td>{{ number_format($advance['allocated_amount'], 2) }} ج.م</td>
                                            <td>{{ $advance['notes'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>
            </div>
        @endif
    </div>
</x-filament-panels::page>
