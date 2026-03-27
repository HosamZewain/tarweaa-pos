<x-filament-panels::page>
    @php
        $logs = $reportData['logs'] ?? collect();
        $summary = $reportData['summary'] ?? [];
        $byActor = $reportData['byActor'] ?? collect();
        $byCustomer = $reportData['byCustomer'] ?? collect();

        $actionLabels = [
            'applied' => 'تطبيق خصم',
            'updated' => 'تعديل خصم',
            'removed' => 'إزالة خصم',
            'configured_on_create' => 'خصم عند إنشاء الطلب',
            'item_applied' => 'خصم على صنف',
            'backfilled_existing_order' => 'خصم تاريخي على طلب',
            'backfilled_existing_item' => 'خصم تاريخي على صنف',
        ];

        $scopeLabels = [
            'order' => 'طلب',
            'item' => 'صنف',
        ];
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            title="سجل الخصومات"
            description="متابعة جميع الخصومات المسجلة في النظام مع من قام بها، الطلبات المتأثرة، والعملاء المرتبطين بها."
            :from="$date_from"
            :to="$date_to"
            :meta="['متابعة رقابية للخصومات', 'العملة الموحدة: ج.م']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر السجل</h3>
                    <p class="admin-filter-card__description">حدد الفترة والمستخدم ونوع الخصم للوصول بسرعة إلى العمليات التي تريد مراجعتها.</p>
                </div>

                <x-admin.badge tone="warning">سجل رقابي</x-admin.badge>
            </div>

            <form wire:submit="generateReport">
                {{ $this->form }}

                <div class="admin-filter-card__actions">
                    <x-filament::button type="submit" icon="heroicon-o-funnel">
                        عرض السجل
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if ($reportData)
            <div class="admin-metric-grid">
                <x-admin.metric-card
                    title="إجمالي أحداث الخصم"
                    :value="number_format($summary['total_events'] ?? 0)"
                    hint="كل عملية خصم أو تعديل تم تسجيلها"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="الطلبات المخصومة"
                    :value="number_format($summary['discounted_orders'] ?? 0)"
                    hint="عدد الطلبات الفريدة المتأثرة بالخصومات"
                    tone="info"
                />

                <x-admin.metric-card
                    title="العملاء المتأثرون"
                    :value="number_format($summary['discounted_clients'] ?? 0)"
                    hint="عدد العملاء أو الطلبات النقدية التي شملتها الخصومات"
                    tone="warning"
                />

                <x-admin.metric-card
                    title="إجمالي قيمة الخصومات"
                    :value="number_format($summary['total_discount_amount'] ?? 0, 2) . ' ج.م'"
                    hint="القيمة الفعلية المحسوبة للخصومات"
                    tone="success"
                />
            </div>

            <x-admin.table-card
                heading="كل أحداث الخصم"
                description="تفصيل كامل لكل خصم مع الطلب والعميل ومن قام بالعملية."
                :count="$logs->count()"
            >
                @if ($logs->isEmpty())
                    <x-admin.empty-state title="لا توجد خصومات ضمن الفلاتر الحالية" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>التوقيت</th>
                                    <th>العملية</th>
                                    <th>النطاق</th>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>الكاشير</th>
                                    <th>تم بواسطة</th>
                                    <th>نوع الخصم</th>
                                    <th>القيمة</th>
                                    <th>الأثر الفعلي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    @php
                                        $order = $log->order;
                                        $customerName = $order?->customer_name ?: ($order?->customer?->name ?? 'بدون عميل');
                                        $actorName = $log->appliedBy?->name ?? 'غير محدد';
                                        $cashierName = $order?->cashier?->name ?? '—';
                                        $typeLabel = $log->discount_type === 'percentage'
                                            ? 'نسبة'
                                            : ($log->discount_type === 'fixed' ? 'مبلغ ثابت' : '—');
                                        $valueLabel = $log->discount_type === 'percentage'
                                            ? number_format($log->discount_value, 2) . '%'
                                            : number_format($log->discount_value, 2) . ' ج.م';
                                    @endphp
                                    <tr>
                                        <td class="text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <x-admin.badge tone="primary">{{ $actionLabels[$log->action] ?? $log->action }}</x-admin.badge>
                                        </td>
                                        <td>
                                            <x-admin.badge tone="info">{{ $scopeLabels[$log->scope] ?? $log->scope }}</x-admin.badge>
                                        </td>
                                        <td class="font-semibold text-gray-900 dark:text-white">
                                            {{ $order?->order_number ?? '—' }}
                                            @if ($log->orderItem?->item_name)
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $log->orderItem->item_name }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ $customerName }}</div>
                                            @if ($order?->customer_phone)
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $order->customer_phone }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $cashierName }}</td>
                                        <td>{{ $actorName }}</td>
                                        <td>{{ $typeLabel }}</td>
                                        <td>{{ $valueLabel }}</td>
                                        <td class="font-bold text-success-600 dark:text-success-400">
                                            {{ number_format($log->discount_amount, 2) }} ج.م
                                            @if (!is_null($log->previous_discount_amount))
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    السابق: {{ number_format($log->previous_discount_amount, 2) }} ج.م
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.table-card>

            <div class="admin-two-column-grid">
                <x-admin.table-card
                    heading="الخصومات حسب المستخدم"
                    description="من الأكثر تطبيقًا للخصومات خلال الفترة."
                    :count="$byActor->count()"
                >
                    @if ($byActor->isEmpty())
                        <x-admin.empty-state title="لا توجد بيانات مستخدمين" />
                    @else
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>عدد العمليات</th>
                                        <th>الطلبات</th>
                                        <th>إجمالي الخصومات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byActor as $row)
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $row['actor'] }}</td>
                                            <td>{{ number_format($row['events']) }}</td>
                                            <td>{{ number_format($row['orders']) }}</td>
                                            <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($row['total_discount_amount'], 2) }} ج.م</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.table-card>

                <x-admin.table-card
                    heading="الخصومات حسب العميل"
                    description="أي العملاء أو الطلبات النقدية حصلوا على خصومات أكثر."
                    :count="$byCustomer->count()"
                >
                    @if ($byCustomer->isEmpty())
                        <x-admin.empty-state title="لا توجد بيانات عملاء" />
                    @else
                        <div class="admin-table-scroll">
                            <table class="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>العميل</th>
                                        <th>الهاتف</th>
                                        <th>عدد العمليات</th>
                                        <th>الطلبات</th>
                                        <th>إجمالي الخصومات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byCustomer as $row)
                                        <tr>
                                            <td class="font-semibold text-gray-900 dark:text-white">{{ $row['customer_name'] }}</td>
                                            <td>{{ $row['customer_phone'] ?: '—' }}</td>
                                            <td>{{ number_format($row['events']) }}</td>
                                            <td>{{ number_format($row['orders']) }}</td>
                                            <td class="font-bold text-success-600 dark:text-success-400">{{ number_format($row['total_discount_amount'], 2) }} ج.م</td>
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
