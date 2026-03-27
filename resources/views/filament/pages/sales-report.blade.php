<x-filament-panels::page>
    @php
        $paymentMethodLabels = [
            'cash' => 'نقد',
            'card' => 'بطاقة',
            'credit_card' => 'بطاقة ائتمان',
            'bank_transfer' => 'تحويل بنكي',
            'wallet' => 'محفظة',
        ];
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير المبيعات"
            description="نظرة شاملة على الأداء اليومي، الأصناف الأعلى مبيعًا، الفئات الأكثر تحقيقًا للإيراد، وطرق الدفع المستخدمة ضمن الفترة المختارة."
            :from="$date_from"
            :to="$date_to"
            :meta="['العملة الموحدة: ج.م', 'محدث تلقائيًا حسب الفلاتر']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">حدد فترة التقرير ثم اعرض النتائج بتنسيق أكثر وضوحًا للمراجعة والتحليل.</p>
                </div>

                <x-admin.badge tone="primary">المبيعات والتفصيل المالي</x-admin.badge>
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
                    :value="number_format($reportData['dailySales']['totals']['total_orders'])"
                    hint="إجمالي عدد الطلبات في الفترة"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="الإيرادات الإجمالية"
                    :value="number_format($reportData['dailySales']['totals']['gross_revenue'], 2) . ' ج.م'"
                    hint="قبل الخصومات والمرتجعات"
                    tone="success"
                />

                <x-admin.metric-card
                    title="إجمالي الخصومات"
                    :value="number_format($reportData['dailySales']['totals']['total_discounts'], 2) . ' ج.م'"
                    hint="إجمالي الخصومات المسجلة"
                    tone="warning"
                />

                <x-admin.metric-card
                    title="صافي الإيرادات"
                    :value="number_format($reportData['dailySales']['totals']['net_revenue'], 2) . ' ج.م'"
                    hint="بعد الخصومات والمرتجعات"
                    tone="info"
                />
            </div>

            <x-admin.table-card
                heading="المبيعات اليومية"
                description="تفصيل يومي للطلبات والإيرادات والخصومات والضريبة وصافي الأداء."
                :count="$reportData['dailySales']['daily']->count()"
            >
                @if ($reportData['dailySales']['daily']->isEmpty())
                    <x-admin.empty-state title="لا توجد بيانات مبيعات في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>التاريخ</th>
                                    <th>الطلبات</th>
                                    <th>الإيرادات</th>
                                    <th>الخصومات</th>
                                    <th>الضريبة</th>
                                    <th>المرتجعات</th>
                                    <th>الصافي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['dailySales']['daily'] as $day)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $day->date }}</td>
                                        <td>{{ number_format($day->total_orders) }}</td>
                                        <td class="font-semibold">{{ number_format($day->gross_revenue, 2) }} ج.م</td>
                                        <td class="font-semibold text-warning-600 dark:text-warning-400">{{ number_format($day->total_discounts, 2) }} ج.م</td>
                                        <td>{{ number_format($day->total_tax, 2) }} ج.م</td>
                                        <td class="font-semibold text-danger-600 dark:text-danger-400">{{ number_format($day->total_refunds, 2) }} ج.م</td>
                                        <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($day->net_revenue, 2) }} ج.م</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>المجموع</td>
                                    <td>{{ number_format($reportData['dailySales']['totals']['total_orders']) }}</td>
                                    <td>{{ number_format($reportData['dailySales']['totals']['gross_revenue'], 2) }} ج.م</td>
                                    <td>{{ number_format($reportData['dailySales']['totals']['total_discounts'], 2) }} ج.م</td>
                                    <td>{{ number_format($reportData['dailySales']['totals']['total_tax'], 2) }} ج.م</td>
                                    <td>{{ number_format($reportData['dailySales']['totals']['total_refunds'], 2) }} ج.م</td>
                                    <td>{{ number_format($reportData['dailySales']['totals']['net_revenue'], 2) }} ج.م</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>

            <x-admin.table-card
                heading="الأصناف الأكثر مبيعًا"
                description="الأصناف الأقوى أداءً خلال الفترة الحالية بحسب الكمية والإيراد."
                :count="$reportData['salesByItem']->count()"
            >
                @if ($reportData['salesByItem']->isEmpty())
                    <x-admin.empty-state title="لا توجد أصناف مباعة في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الصنف</th>
                                    <th>الكمية</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['salesByItem'] as $i => $item)
                                    <tr>
                                        <td>
                                            <x-admin.badge tone="neutral">{{ $i + 1 }}</x-admin.badge>
                                        </td>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $item->item_name }}</td>
                                        <td>{{ number_format($item->total_quantity) }}</td>
                                        <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($item->net_revenue, 2) }} ج.م</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>

            <x-admin.table-card
                heading="المبيعات حسب الفئة"
                description="مقارنة بين الفئات الرئيسية مع نسبة مساهمة كل فئة في إجمالي الإيراد."
                :count="$reportData['salesByCategory']->count()"
            >
                @if ($reportData['salesByCategory']->isEmpty())
                    <x-admin.empty-state title="لا توجد فئات مباعة في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الفئة</th>
                                    <th>الكمية</th>
                                    <th>الإيرادات</th>
                                    <th>المساهمة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['salesByCategory'] as $cat)
                                    @php
                                        $share = $reportData['dailySales']['totals']['gross_revenue'] > 0
                                            ? round(($cat->net_revenue / $reportData['dailySales']['totals']['gross_revenue']) * 100, 1)
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $cat->category_name }}</td>
                                        <td>{{ number_format($cat->total_quantity) }}</td>
                                        <td class="font-semibold">{{ number_format($cat->net_revenue, 2) }} ج.م</td>
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

            <x-admin.table-card
                heading="المبيعات حسب طريقة الدفع"
                description="توزيع التحصيل بحسب قنوات الدفع الأكثر استخدامًا خلال الفترة."
                :count="$reportData['salesByPayment']->count()"
            >
                @if ($reportData['salesByPayment']->isEmpty())
                    <x-admin.empty-state title="لا توجد عمليات دفع في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>طريقة الدفع</th>
                                    <th>عدد المعاملات</th>
                                    <th>المبلغ الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['salesByPayment'] as $pay)
                                    @php
                                        $methodKey = $pay->payment_method instanceof \BackedEnum
                                            ? $pay->payment_method->value
                                            : $pay->payment_method;
                                        $methodLabel = method_exists($pay->payment_method, 'label')
                                            ? $pay->payment_method->label()
                                            : ($paymentMethodLabels[$methodKey] ?? $methodKey);
                                    @endphp
                                    <tr>
                                        <td>
                                            <x-admin.badge tone="primary">{{ $methodLabel }}</x-admin.badge>
                                        </td>
                                        <td>{{ number_format($pay->transaction_count) }}</td>
                                        <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($pay->total_amount, 2) }} ج.م</td>
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
