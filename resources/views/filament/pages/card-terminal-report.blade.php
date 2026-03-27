<x-filament-panels::page>
    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير أجهزة الدفع"
            description="متابعة إجمالي عمليات البطاقة ورسوم كل جهاز وصافي التسوية المتوقع لكل بنك أو ماكينة."
            :from="$date_from"
            :to="$date_to"
            :meta="['عمليات البطاقة فقط', 'العملة الموحدة: ج.م']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">اختر الفترة الزمنية لمراجعة إجمالي التحصيل عبر أجهزة الدفع ورسومها وصافي التسوية.</p>
                </div>

                <x-admin.badge tone="primary">أجهزة الدفع والرسوم</x-admin.badge>
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
                    title="عدد الأجهزة"
                    :value="number_format($reportData['totals']['terminal_count'])"
                    hint="الأجهزة التي استقبلت عمليات بطاقات"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="إجمالي عمليات البطاقة"
                    :value="number_format($reportData['totals']['total_paid_amount'], 2) . ' ج.م'"
                    hint="إجمالي مبالغ البطاقة قبل الرسوم"
                    tone="success"
                />

                <x-admin.metric-card
                    title="إجمالي الرسوم"
                    :value="number_format($reportData['totals']['total_fee_amount'], 2) . ' ج.م'"
                    hint="الرسوم المخصومة من التسوية"
                    tone="warning"
                />

                <x-admin.metric-card
                    title="صافي التسوية"
                    :value="number_format($reportData['totals']['total_net_settlement'], 2) . ' ج.م'"
                    hint="المبلغ المتوقع بعد خصم الرسوم"
                    tone="info"
                />
            </div>

            <x-admin.table-card
                heading="ملخص الأجهزة"
                description="تفصيل المعاملات والرسوم وصافي التسوية حسب كل جهاز دفع."
                :count="$reportData['terminals']->count()"
            >
                @if ($reportData['terminals']->isEmpty())
                    <x-admin.empty-state title="لا توجد عمليات بطاقات في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الجهاز</th>
                                    <th>البنك</th>
                                    <th>الكود</th>
                                    <th>المعاملات</th>
                                    <th>إجمالي التحصيل</th>
                                    <th>إجمالي الرسوم</th>
                                    <th>صافي التسوية</th>
                                    <th>معدل الرسوم</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['terminals'] as $terminal)
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $terminal['terminal_name'] }}</td>
                                        <td>{{ $terminal['bank_name'] ?: '—' }}</td>
                                        <td>{{ $terminal['terminal_code'] ?: '—' }}</td>
                                        <td>{{ number_format($terminal['transaction_count']) }}</td>
                                        <td class="font-semibold">{{ number_format($terminal['total_paid_amount'], 2) }} ج.م</td>
                                        <td class="font-semibold text-warning-600 dark:text-warning-400">{{ number_format($terminal['total_fee_amount'], 2) }} ج.م</td>
                                        <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($terminal['total_net_settlement'], 2) }} ج.م</td>
                                        <td>{{ number_format($terminal['effective_fee_rate'], 2) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>الإجمالي</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>{{ number_format($reportData['totals']['transaction_count']) }}</td>
                                    <td>{{ number_format($reportData['totals']['total_paid_amount'], 2) }} ج.م</td>
                                    <td>{{ number_format($reportData['totals']['total_fee_amount'], 2) }} ج.م</td>
                                    <td>{{ number_format($reportData['totals']['total_net_settlement'], 2) }} ج.م</td>
                                    <td>—</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>
        @endif
    </div>
</x-filament-panels::page>
