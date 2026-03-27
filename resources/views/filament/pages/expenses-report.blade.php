<x-filament-panels::page>
    @php
        $paymentMethods = [
            'cash' => 'نقد',
            'bank_transfer' => 'تحويل بنكي',
            'credit_card' => 'بطاقة ائتمان',
        ];
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير المصروفات"
            description="تحليل المصروفات حسب الفئة والحالة وطريقة الدفع مع عرض تفصيلي للمبالغ المعتمدة والمعلقة."
            :from="$date_from"
            :to="$date_to"
            :meta="['متابعة الاعتماد المالي', 'العملة الموحدة: ج.م']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">اختر الفترة الزمنية لعرض ملخص المصروفات وتفاصيلها بصورة أوضح.</p>
                </div>

                <x-admin.badge tone="danger">متابعة مالية</x-admin.badge>
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
                    title="عدد المصروفات"
                    :value="number_format($reportData['totals']['total_expenses'])"
                    hint="عدد الحركات المسجلة"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="الإجمالي الكلي"
                    :value="number_format($reportData['totals']['total_amount'], 2) . ' ج.م'"
                    hint="إجمالي المصروفات في الفترة"
                    tone="danger"
                />

                <x-admin.metric-card
                    title="المعتمد"
                    :value="number_format($reportData['totals']['approved_amount'], 2) . ' ج.م'"
                    hint="مصروفات تم اعتمادها"
                    tone="success"
                />

                <x-admin.metric-card
                    title="بانتظار الاعتماد"
                    :value="number_format($reportData['totals']['pending_amount'], 2) . ' ج.م'"
                    hint="يتطلب مراجعة واعتماد"
                    tone="warning"
                />
            </div>

            @if ($reportData['byCategory']->count() > 0)
                <x-admin.table-card
                    heading="توزيع المصروفات حسب التصنيف"
                    description="يعرض نسبة مساهمة كل فئة من فئات المصروفات في الإجمالي الكلي."
                    :count="$reportData['byCategory']->count()"
                >
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>التصنيف</th>
                                    <th>عدد المعاملات</th>
                                    <th>الإجمالي</th>
                                    <th>النسبة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['byCategory'] as $category => $data)
                                    @php
                                        $share = $reportData['totals']['total_amount'] > 0
                                            ? round(($data['total'] / $reportData['totals']['total_amount']) * 100, 1)
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td class="font-semibold text-gray-900 dark:text-white">{{ $category }}</td>
                                        <td>{{ number_format($data['count']) }}</td>
                                        <td>{{ number_format($data['total'], 2) }} ج.م</td>
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
                </x-admin.table-card>
            @endif

            <x-admin.table-card
                heading="تفاصيل المصروفات"
                description="سجل تفصيلي للمصروفات مع الحالة وطريقة الدفع والتاريخ."
                :count="$reportData['expenses']->count()"
            >
                @if ($reportData['expenses']->isEmpty())
                    <x-admin.empty-state title="لا توجد مصروفات في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>رقم المصروف</th>
                                    <th>التصنيف</th>
                                    <th>الوصف</th>
                                    <th>المبلغ</th>
                                    <th>طريقة الدفع</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['expenses'] as $expense)
                                    <tr>
                                        @php
                                            $expensePaymentKey = $expense->payment_method instanceof \BackedEnum
                                                ? $expense->payment_method->value
                                                : $expense->payment_method;
                                        @endphp
                                        <td class="font-mono font-semibold text-gray-900 dark:text-white">{{ $expense->expense_number }}</td>
                                        <td>{{ $expense->category->name ?? '—' }}</td>
                                        <td>
                                            <div class="max-w-xs truncate" title="{{ $expense->description }}">
                                                {{ $expense->description }}
                                            </div>
                                        </td>
                                        <td class="font-bold">{{ number_format($expense->amount, 2) }} ج.م</td>
                                        <td>
                                            <x-admin.badge tone="info">
                                                {{ method_exists($expense->payment_method, 'label') ? $expense->payment_method->label() : ($paymentMethods[$expensePaymentKey] ?? $expensePaymentKey) }}
                                            </x-admin.badge>
                                        </td>
                                        <td class="text-gray-500 dark:text-gray-400">{{ $expense->expense_date->format('Y-m-d') }}</td>
                                        <td>
                                            @if ($expense->approved_by)
                                                <x-admin.badge tone="success">معتمد</x-admin.badge>
                                            @else
                                                <x-admin.badge tone="warning">بانتظار الاعتماد</x-admin.badge>
                                            @endif
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
