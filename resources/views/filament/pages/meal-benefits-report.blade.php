<x-filament-panels::page>
    <div class="admin-page-shell">
        <x-admin.report-header
            title="كشف بدلات الوجبات والتحميل"
            description="مراجعة ملفات مزايا الموظفين والمالكين، واستهلاك البدل الشهري، والوجبات المجانية، والتحميل على الحساب ضمن الشهر المختار."
            :meta="[$reportData['period_label'] ?? '']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر الكشف</h3>
                    <p class="admin-filter-card__description">حدد الشهر، ونوع الحركة، والمستخدم عند الحاجة لمراجعة كشف تفصيلي أو رصيد مستخدم محدد.</p>
                </div>

                <x-admin.badge tone="info">كشف شهري وتشغيلي</x-admin.badge>
            </div>

            <form wire:submit="generateReport">
                {{ $this->form }}

                <div class="admin-filter-card__actions">
                    <x-filament::button type="submit" icon="heroicon-o-funnel">
                        عرض الكشف
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if ($reportData)
            <div class="admin-metric-grid xl:grid-cols-6">
                <x-admin.metric-card
                    title="الحركات"
                    :value="number_format($reportData['summary_cards']['entries_count'])"
                    hint="إجمالي قيود الدفتر في الفترة"
                    tone="primary"
                />
                <x-admin.metric-card
                    title="تحميل المالك / الإدارة"
                    :value="number_format($reportData['summary_cards']['owner_charge_amount'], 2) . ' ج.م'"
                    hint="قيمة الطلبات المحملة على الحساب"
                    tone="warning"
                />
                <x-admin.metric-card
                    title="استهلاك البدل الشهري"
                    :value="number_format($reportData['summary_cards']['allowance_amount'], 2) . ' ج.م'"
                    hint="القيمة المغطاة من البدل"
                    tone="info"
                />
                <x-admin.metric-card
                    title="استهلاك الوجبات المجانية"
                    :value="number_format($reportData['summary_cards']['free_meal_amount'], 2) . ' ج.م'"
                    hint="القيمة المغطاة من الوجبات المجانية"
                    tone="success"
                />
                <x-admin.metric-card
                    title="عدد الوجبات المستخدمة"
                    :value="number_format($reportData['summary_cards']['free_meal_count'])"
                    hint="إجمالي الوجبات المجانية المستخدمة"
                    tone="success"
                />
                <x-admin.metric-card
                    title="المدفوع التكميلي"
                    :value="number_format($reportData['summary_cards']['supplemental_payment_amount'], 2) . ' ج.م'"
                    hint="فروق السداد التي دفعها المستفيد"
                    tone="danger"
                />
            </div>

            @if ($reportData['selected_user'])
                <div class="admin-metric-grid xl:grid-cols-4">
                    <x-admin.metric-card
                        title="المستخدم المحدد"
                        :value="$reportData['selected_user']['name']"
                        :hint="$reportData['selected_user_summary']['profile_mode'] ?? 'بدون ملف نشط'"
                        tone="primary"
                    />
                    <x-admin.metric-card
                        title="المتبقي من البدل"
                        :value="number_format($reportData['selected_user_summary']['monthly_allowance_remaining'] ?? 0, 2) . ' ج.م'"
                        :hint="'المستهلك: ' . number_format($reportData['selected_user_summary']['monthly_allowance_used'] ?? 0, 2) . ' ج.م'"
                        tone="info"
                    />
                    <x-admin.metric-card
                        title="المتبقي من حد المبلغ"
                        :value="number_format($reportData['selected_user_summary']['free_meal_amount_remaining'] ?? 0, 2) . ' ج.م'"
                        :hint="'المستهلك: ' . number_format($reportData['selected_user_summary']['free_meal_amount_used'] ?? 0, 2) . ' ج.م'"
                        tone="success"
                    />
                    <x-admin.metric-card
                        title="المتبقي من عدد الوجبات"
                        :value="number_format($reportData['selected_user_summary']['free_meal_count_remaining'] ?? 0)"
                        :hint="'المستخدم: ' . number_format($reportData['selected_user_summary']['free_meal_count_used'] ?? 0)"
                        tone="warning"
                    />
                </div>
            @endif

            <div class="grid gap-6 xl:grid-cols-2">
                <x-admin.table-card
                    heading="كشف التحميل على المالك / الإدارة"
                    description="سجل الطلبات المحملة على الحساب خلال الشهر المختار."
                    :count="count($reportData['owner_charge_statement']['rows'])"
                >
                    @if (empty($reportData['owner_charge_statement']['rows']))
                        <x-admin.empty-state title="لا توجد عمليات تحميل على الحساب في هذه الفترة" />
                    @else
                        <div class="mb-4">
                            <x-admin.badge tone="warning">
                                الإجمالي: {{ number_format($reportData['owner_charge_statement']['total_amount'], 2) }} ج.م
                            </x-admin.badge>
                        </div>
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>المستخدم</th>
                                        <th>رقم الطلب</th>
                                        <th>المبلغ المحمل</th>
                                        <th>ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData['owner_charge_statement']['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['date'] }}</td>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $row['user_name'] }}</td>
                                            <td>
                                                @if ($row['order_number'] && $row['order_url'])
                                                    <a href="{{ $row['order_url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                                        {{ $row['order_number'] }}
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="font-semibold">{{ number_format($row['charged_amount'], 2) }} ج.م</td>
                                            <td>{{ $row['notes'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>

                <x-admin.table-card
                    heading="تقرير البدل الشهري للموظفين"
                    description="يعرض المبلغ المخصص، المستهلك، المتبقي، وعدد الطلبات المغطاة وفروق السداد."
                    :count="count($reportData['allowance_report']['rows'])"
                >
                    @if (empty($reportData['allowance_report']['rows']))
                        <x-admin.empty-state title="لا توجد ملفات بدل شهري نشطة في هذه الفترة" />
                    @else
                        <div class="mb-4 flex flex-wrap gap-2">
                            <x-admin.badge tone="info">المستهلك: {{ number_format($reportData['allowance_report']['totals']['consumed_amount'], 2) }} ج.م</x-admin.badge>
                            <x-admin.badge tone="success">المتبقي: {{ number_format($reportData['allowance_report']['totals']['remaining_amount'], 2) }} ج.م</x-admin.badge>
                            <x-admin.badge tone="danger">فروق مدفوعة: {{ number_format($reportData['allowance_report']['totals']['paid_differences_amount'], 2) }} ج.م</x-admin.badge>
                        </div>
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>البدل الشهري</th>
                                        <th>المستهلك</th>
                                        <th>المتبقي</th>
                                        <th>طلبات مغطاة</th>
                                        <th>فروق مدفوعة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData['allowance_report']['rows'] as $row)
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $row['user_name'] }}</td>
                                            <td>{{ number_format($row['configured_monthly_allowance'], 2) }} ج.م</td>
                                            <td class="font-semibold text-info-600 dark:text-info-400">{{ number_format($row['consumed_amount'], 2) }} ج.م</td>
                                            <td class="font-semibold text-success-600 dark:text-success-400">{{ number_format($row['remaining_amount'], 2) }} ج.م</td>
                                            <td>{{ number_format($row['covered_orders_count']) }}</td>
                                            <td class="font-semibold text-danger-600 dark:text-danger-400">{{ number_format($row['paid_differences_amount'], 2) }} ج.م</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <x-admin.table-card
                    heading="تقرير الوجبات المجانية"
                    description="يعرض نوع الحد المطبق، حدود الاستخدام الشهرية، ما تم استهلاكه، والمتبقي لكل موظف."
                    :count="count($reportData['free_meal_report']['rows'])"
                >
                    @if (empty($reportData['free_meal_report']['rows']))
                        <x-admin.empty-state title="لا توجد ملفات وجبات مجانية نشطة في هذه الفترة" />
                    @else
                        <div class="mb-4 flex flex-wrap gap-2">
                            <x-admin.badge tone="success">استهلاك بالمبلغ: {{ number_format($reportData['free_meal_report']['totals']['consumed_amount'], 2) }} ج.م</x-admin.badge>
                            <x-admin.badge tone="warning">عدد الوجبات: {{ number_format($reportData['free_meal_report']['totals']['consumed_count']) }}</x-admin.badge>
                            <x-admin.badge tone="primary">طلبات مغطاة: {{ number_format($reportData['free_meal_report']['totals']['covered_orders_count']) }}</x-admin.badge>
                        </div>
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>نوع الحد</th>
                                        <th>الحد المهيأ</th>
                                        <th>المستهلك بالمبلغ</th>
                                        <th>المتبقي بالمبلغ</th>
                                        <th>المستخدم / المتبقي بالعدد</th>
                                        <th>طلبات مغطاة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData['free_meal_report']['rows'] as $row)
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $row['user_name'] }}</td>
                                            <td><x-admin.badge tone="success">{{ $row['benefit_type'] }}</x-admin.badge></td>
                                            <td>{{ $row['configured_limit'] }}</td>
                                            <td>{{ number_format($row['consumed_amount'], 2) }} ج.م</td>
                                            <td>{{ number_format($row['remaining_amount'], 2) }} ج.م</td>
                                            <td>{{ number_format($row['consumed_count']) }} / {{ number_format($row['remaining_count']) }}</td>
                                            <td>{{ number_format($row['covered_orders_count']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>

                <x-admin.table-card
                    heading="تقرير التغطية الجزئية وفروق السداد"
                    description="يعرض الطلبات التي غطت فيها المزايا جزءًا من القيمة، بينما دُفع الباقي بشكل تكميلي."
                    :count="count($reportData['mixed_coverage_report']['rows'])"
                >
                    @if (empty($reportData['mixed_coverage_report']['rows']))
                        <x-admin.empty-state title="لا توجد حالات تغطية جزئية في هذه الفترة" />
                    @else
                        <div class="mb-4 flex flex-wrap gap-2">
                            <x-admin.badge tone="info">مغطى: {{ number_format($reportData['mixed_coverage_report']['totals']['covered_amount'], 2) }} ج.م</x-admin.badge>
                            <x-admin.badge tone="danger">فروق مدفوعة: {{ number_format($reportData['mixed_coverage_report']['totals']['paid_differences_amount'], 2) }} ج.م</x-admin.badge>
                        </div>
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>الموظف</th>
                                        <th>رقم الطلب</th>
                                        <th>نوع التسوية</th>
                                        <th>إجمالي الطلب</th>
                                        <th>المبلغ المغطى</th>
                                        <th>الفرق المدفوع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData['mixed_coverage_report']['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['date'] }}</td>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $row['user_name'] }}</td>
                                            <td>
                                                @if ($row['order_number'] && $row['order_url'])
                                                    <a href="{{ $row['order_url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                                        {{ $row['order_number'] }}
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td><x-admin.badge tone="info">{{ $row['settlement_type'] }}</x-admin.badge></td>
                                            <td>{{ number_format($row['order_total'], 2) }} ج.م</td>
                                            <td class="font-semibold text-success-600 dark:text-success-400">{{ number_format($row['covered_amount'], 2) }} ج.م</td>
                                            <td class="font-semibold text-danger-600 dark:text-danger-400">{{ number_format($row['paid_difference'], 2) }} ج.م</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>
            </div>

            <x-admin.table-card
                heading="حركات دفتر المزايا"
                description="سجل تفصيلي لكل استخدام أو تحميل أو دفع تكميلي ضمن الفترة الحالية."
                :count="count($reportData['entries'])"
            >
                @if (empty($reportData['entries']))
                    <x-admin.empty-state title="لا توجد حركات في الفلاتر الحالية" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الوقت</th>
                                    <th>المستخدم</th>
                                    <th>نوع الحركة</th>
                                    <th>المبلغ</th>
                                    <th>عدد الوجبات</th>
                                    <th>الطلب</th>
                                    <th>الصنف المؤهل</th>
                                    <th>الكمية المغطاة</th>
                                    <th>ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['entries'] as $entry)
                                    <tr>
                                        <td>{{ $entry['created_at'] }}</td>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $entry['user_name'] }}</td>
                                        <td>
                                            <x-admin.badge tone="primary">{{ $entry['entry_type'] }}</x-admin.badge>
                                        </td>
                                        <td class="font-semibold">{{ number_format($entry['amount'], 2) }} ج.م</td>
                                        <td>{{ number_format($entry['meals_count']) }}</td>
                                        <td>
                                            @if ($entry['order_number'] && $entry['order_url'])
                                                <a href="{{ $entry['order_url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $entry['order_number'] }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $entry['menu_item_name'] ?: '—' }}</td>
                                        <td>{{ $entry['covered_quantity'] ?? '—' }}</td>
                                        <td>{{ $entry['notes'] ?: '—' }}</td>
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
