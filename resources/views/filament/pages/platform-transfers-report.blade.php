<x-filament-panels::page>
    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير تحويلات المنصات"
            description="متابعة عمليات الدفع غير النقدية التي لا تدخل الدرج النقدي، مثل طلبات وجاهز وإنستاباي والدفع الإلكتروني."
            :from="$date_from"
            :to="$date_to"
            :meta="['المرجع محفوظ مع كل عملية عند توفره', 'يعتمد على قيود order_payments الفعلية']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">اختر الفترة وطرق الدفع غير النقدية التي تريد متابعتها ضمن تحويلات المنصات أو التحويلات البنكية المباشرة.</p>
                </div>

                <x-admin.badge tone="info">تحويلات خارج الدرج</x-admin.badge>
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
            <div class="admin-metric-grid xl:grid-cols-4">
                <x-admin.metric-card
                    title="إجمالي التحويلات"
                    :value="number_format($reportData['summary']['total_amount'], 2) . ' ج.م'"
                    hint="مجموع كل الطرق المختارة"
                    tone="primary"
                />
                <x-admin.metric-card
                    title="عدد العمليات"
                    :value="number_format($reportData['summary']['transactions_count'])"
                    hint="إجمالي قيود الدفع المسجلة"
                    tone="info"
                />
                <x-admin.metric-card
                    title="تحويلات المنصات"
                    :value="number_format($reportData['summary']['platform_amount'], 2) . ' ج.م'"
                    hint="طلبات، جاهز، والدفع الإلكتروني"
                    tone="warning"
                />
                <x-admin.metric-card
                    title="إنستاباي"
                    :value="number_format($reportData['summary']['instapay_amount'], 2) . ' ج.م'"
                    hint="تحويلات إنستاباي المباشرة"
                    tone="success"
                />
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <x-admin.table-card
                    heading="ملخص حسب طريقة الدفع"
                    description="يبين إجمالي وقيمة العمليات لكل قناة دفع غير نقدية."
                    :count="$reportData['by_method']->count()"
                >
                    @if ($reportData['by_method']->isEmpty())
                        <x-admin.empty-state title="لا توجد عمليات مطابقة للفلاتر الحالية" />
                    @else
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>طريقة الدفع</th>
                                        <th>عدد العمليات</th>
                                        <th>الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData['by_method'] as $row)
                                        <tr>
                                            <td><x-admin.badge tone="info">{{ $row['payment_method_label'] }}</x-admin.badge></td>
                                            <td>{{ number_format($row['transactions_count']) }}</td>
                                            <td class="font-semibold">{{ number_format($row['total_amount'], 2) }} ج.م</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>

                <x-admin.table-card
                    heading="إجمالي يومي"
                    description="مفيد لمطابقة التحويلات اليومية أو تجميعها قبل التسوية الدورية."
                    :count="$reportData['daily_totals']->count()"
                >
                    @if ($reportData['daily_totals']->isEmpty())
                        <x-admin.empty-state title="لا توجد تحويلات في هذه الفترة" />
                    @else
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>عدد العمليات</th>
                                        <th>الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reportData['daily_totals'] as $row)
                                        <tr>
                                            <td>{{ $row['date'] }}</td>
                                            <td>{{ number_format($row['transactions_count']) }}</td>
                                            <td class="font-semibold">{{ number_format($row['total_amount'], 2) }} ج.م</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>
            </div>

            <x-admin.table-card
                heading="تفصيل العمليات"
                description="كل عملية دفع غير نقدية مع رقم الطلب والمصدر والمرجع والكاشير المسؤول عن تسجيلها."
                :count="$reportData['entries']->count()"
            >
                @if ($reportData['entries']->isEmpty())
                    <x-admin.empty-state title="لا توجد عمليات مطابقة للفلاتر الحالية" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>التاريخ والوقت</th>
                                    <th>رقم الطلب</th>
                                    <th>المصدر</th>
                                    <th>طريقة الدفع</th>
                                    <th>الإجمالي</th>
                                    <th>المرجع</th>
                                    <th>الكاشير</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['entries'] as $entry)
                                    <tr>
                                        <td>{{ $entry['date_time'] }}</td>
                                        <td>
                                            @if ($entry['order_number'] && $entry['order_url'])
                                                <a href="{{ $entry['order_url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $entry['order_number'] }}
                                                </a>
                                            @else
                                                —
                                            @endif

                                            @if ($entry['external_order_number'])
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    مرجع خارجي: {{ $entry['external_order_number'] }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ $entry['order_source'] }}</td>
                                        <td><x-admin.badge tone="primary">{{ $entry['payment_method_label'] }}</x-admin.badge></td>
                                        <td class="font-semibold">{{ number_format($entry['amount'], 2) }} ج.م</td>
                                        <td>{{ $entry['reference_number'] ?: '—' }}</td>
                                        <td>{{ $entry['cashier_name'] }}</td>
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
