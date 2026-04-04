<x-filament-panels::page>
    @php
        $summary = $reportData['summary'] ?? [];
        $roleBreakdown = collect($reportData['role_breakdown'] ?? []);
        $currentSalaries = collect($reportData['current_salaries'] ?? []);
        $recentPenalties = collect($reportData['recent_penalties'] ?? []);
        $recentHires = collect($reportData['recent_hires'] ?? []);
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            eyebrow="HR"
            title="نظرة عامة HR"
            description="مؤشرات سريعة عن الموظفين، الرواتب، الجزاءات، وملفات المزايا من نقطة واحدة."
            :meta="[
                'صورة حالية وليست تقرير فترة زمنية',
                'تعتمد على الموظفين التشغيليين فقط',
                'تعرض الرواتب السارية والجزاءات النشطة',
            ]"
        />

        <div class="admin-metric-grid">
            <x-admin.metric-card title="إجمالي الموظفين" :value="number_format($summary['total_employees'] ?? 0)" hint="كل الموظفين داخل قسم HR" tone="primary" />
            <x-admin.metric-card title="موظفون نشطون" :value="number_format($summary['active_employees'] ?? 0)" hint="الحسابات النشطة حاليًا" tone="success" />
            <x-admin.metric-card title="موظفون غير نشطين" :value="number_format($summary['inactive_employees'] ?? 0)" hint="حسابات متوقفة أو غير مفعلة" tone="danger" />
            <x-admin.metric-card title="ملفات وظيفية مكتملة" :value="number_format($summary['employees_with_profiles'] ?? 0)" hint="الموظفون الذين لديهم ملف وظيفي" tone="info" />
            <x-admin.metric-card title="رواتب سارية" :value="number_format($summary['employees_with_current_salary'] ?? 0)" hint="موظفون لديهم راتب حالي" tone="warning" />
            <x-admin.metric-card title="إجمالي الرواتب الحالية" :value="number_format($summary['total_current_salaries'] ?? 0, 2) . ' ج.م'" hint="مجموع الرواتب السارية" tone="success" />
            <x-admin.metric-card title="متوسط الراتب الحالي" :value="number_format($summary['average_current_salary'] ?? 0, 2) . ' ج.م'" hint="المتوسط على الموظفين الذين لديهم راتب" tone="neutral" />
            <x-admin.metric-card title="جزاءات نشطة" :value="number_format($summary['active_penalties_count'] ?? 0)" hint="إجمالي الجزاءات المفعلة" tone="danger" />
            <x-admin.metric-card title="إجمالي الجزاءات" :value="number_format($summary['active_penalties_total'] ?? 0, 2) . ' ج.م'" hint="القيمة الإجمالية للجزاءات النشطة" tone="danger" />
            <x-admin.metric-card title="ملفات مزايا نشطة" :value="number_format($summary['active_benefit_profiles'] ?? 0)" hint="موظفون لديهم مزايا وجبات/تحميل" tone="info" />
            <x-admin.metric-card title="بدل موظف" :value="number_format($summary['allowance_profiles'] ?? 0)" hint="ملفات بدل نشطة" tone="warning" />
            <x-admin.metric-card title="تعيينات هذا الشهر" :value="number_format($summary['hired_this_month'] ?? 0)" hint="حسب تاريخ التعيين في الملف الوظيفي" tone="primary" />
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card heading="توزيع الموظفين حسب الدور" description="الصورة الحالية حسب الدور التشغيلي الأساسي." :count="$roleBreakdown->count()">
                @if ($roleBreakdown->isEmpty())
                    <x-admin.empty-state title="لا توجد بيانات أدوار" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الدور</th>
                                    <th>إجمالي الموظفين</th>
                                    <th>النشطون</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roleBreakdown as $row)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $row['role_label'] }}</td>
                                        <td>{{ number_format($row['employees_count']) }}</td>
                                        <td>{{ number_format($row['active_count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>

            <x-admin.table-card heading="مؤشرات المزايا" description="ملفات مزايا الوجبات والتحميل النشطة." :count="$summary['active_benefit_profiles'] ?? 0">
                <div class="admin-metric-grid xl:grid-cols-3">
                    <x-admin.metric-card title="تحميل مالك" :value="number_format($summary['owner_charge_profiles'] ?? 0)" hint="مسموح لهم التحميل على حساب المالك" tone="neutral" />
                    <x-admin.metric-card title="بدل موظف" :value="number_format($summary['allowance_profiles'] ?? 0)" hint="بدل مبلغ دوري" tone="warning" />
                    <x-admin.metric-card title="وجبة مجانية" :value="number_format($summary['free_meal_profiles'] ?? 0)" hint="ملفات وجبات مجانية نشطة" tone="success" />
                </div>
            </x-admin.table-card>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card heading="الرواتب الحالية" description="الموظفون الذين لديهم راتب ساري حاليًا." :count="$currentSalaries->count()">
                @if ($currentSalaries->isEmpty())
                    <x-admin.empty-state title="لا توجد رواتب سارية حاليًا" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الموظف</th>
                                    <th>المسمى الوظيفي</th>
                                    <th>الراتب</th>
                                    <th>ساري من</th>
                                    <th>ساري حتى</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($currentSalaries as $row)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['job_title'] ?: '—' }}</td>
                                        <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($row['amount'], 2) }} ج.م</td>
                                        <td>{{ $row['effective_from'] ?: '—' }}</td>
                                        <td>{{ $row['effective_to'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>

            <x-admin.table-card heading="أحدث الجزاءات" description="آخر الجزاءات المسجلة على الموظفين." :count="$recentPenalties->count()">
                @if ($recentPenalties->isEmpty())
                    <x-admin.empty-state title="لا توجد جزاءات مسجلة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الموظف</th>
                                    <th>التاريخ</th>
                                    <th>السبب</th>
                                    <th>القيمة</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentPenalties as $row)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $row['employee_name'] }}</td>
                                        <td>{{ $row['penalty_date'] ?: '—' }}</td>
                                        <td>{{ $row['reason'] }}</td>
                                        <td>{{ number_format($row['amount'], 2) }} ج.م</td>
                                        <td>{{ $row['is_active'] ? 'نشط' : 'غير نشط' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>
        </div>

        <x-admin.table-card heading="أحدث التعيينات" description="أحدث الموظفين حسب تاريخ التعيين في الملف الوظيفي." :count="$recentHires->count()">
            @if ($recentHires->isEmpty())
                <x-admin.empty-state title="لا توجد تواريخ تعيين مسجلة" />
            @else
                <div class="admin-table-scroll">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>المسمى الوظيفي</th>
                                <th>تاريخ التعيين</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentHires as $row)
                                <tr>
                                    <td class="font-semibold text-gray-900 dark:text-white">{{ $row['employee_name'] }}</td>
                                    <td>{{ $row['job_title'] ?: '—' }}</td>
                                    <td>{{ $row['hired_at'] ?: '—' }}</td>
                                    <td>{{ $row['is_active'] ? 'نشط' : 'غير نشط' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.table-card>
    </div>
</x-filament-panels::page>
