<x-filament-panels::page>
    @php($record = $this->getRecord())

    <div class="space-y-6">
        <x-admin.report-header
            eyebrow="وردية"
            :title="'ملف الوردية ' . $record->shift_number"
            :description="$this->getSubtitle()"
            :meta="[
                'الحالة: ' . $record->status->label(),
                'بدأت: ' . $this->formatDateTime($record->started_at),
                'أغلقها: ' . ($record->closer?->name ?? '—'),
                'الأدراج: ' . $this->formatNumber($record->drawerSessions->count()),
            ]"
        />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->getPrimaryStats() as $stat)
                <x-admin.metric-card
                    :title="$stat['title']"
                    :value="$stat['value']"
                    :hint="$stat['hint']"
                    :tone="$stat['tone']"
                />
            @endforeach
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->getSecondaryStats() as $stat)
                <x-admin.metric-card
                    :title="$stat['title']"
                    :value="$stat['value']"
                    :hint="$stat['hint']"
                    :tone="$stat['tone']"
                />
            @endforeach
        </section>

        <div class="grid gap-6 xl:grid-cols-12">
            <section class="admin-table-card xl:col-span-4">
                <div class="admin-table-card__header">
                    <div>
                        <h3 class="admin-table-card__title">نظرة تشغيلية</h3>
                        <p class="admin-table-card__description">أهم تفاصيل الوردية وهيكل التشغيل المرتبط بها.</p>
                    </div>

                    <x-admin.badge :tone="$this->shiftStatusTone($record->status)">{{ $record->status->label() }}</x-admin.badge>
                </div>

                <div class="admin-table-card__body">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        @foreach ($this->getOperationalSnapshot() as $row)
                            <div class="rounded-2xl border border-gray-200/70 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5 {{ $row['label'] === 'ملاحظات' ? 'sm:col-span-2' : '' }}">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                                <dd class="mt-2 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                    @if (isset($row['tone']))
                                        <x-admin.badge :tone="$row['tone']">{{ $row['value'] }}</x-admin.badge>
                                    @else
                                        <span>{{ $row['value'] }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            <section class="admin-table-card xl:col-span-4">
                <div class="admin-table-card__header">
                    <div>
                        <h3 class="admin-table-card__title">الملخص المالي</h3>
                        <p class="admin-table-card__description">صورة مكثفة للنقد والمصروفات والرسوم داخل الوردية.</p>
                    </div>

                    <x-admin.badge :tone="$this->differenceTone($record->cash_difference)">
                        {{ $this->formatMoney($record->cash_difference) }}
                    </x-admin.badge>
                </div>

                <div class="admin-table-card__body">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        @foreach ($this->getFinancialSnapshot() as $row)
                            <div class="rounded-2xl border border-gray-200/70 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                                <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            <section class="space-y-6 xl:col-span-4">
                <x-admin.table-card heading="الإحصاءات التشغيلية" description="توزيع حالات الطلبات خلال الوردية.">
                    @if (filled($this->getOrderStatusStats()))
                        <div class="grid gap-3">
                            @foreach ($this->getOrderStatusStats() as $row)
                                <div class="flex items-center justify-between rounded-2xl border border-gray-200/70 bg-white/70 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                                    <x-admin.badge :tone="$row['tone']">{{ $row['label'] }}</x-admin.badge>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $this->formatNumber($row['value']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-admin.empty-state
                            title="لا توجد طلبات على هذه الوردية بعد"
                            description="عند بدء البيع ستظهر هنا الإحصاءات التشغيلية لكل حالة طلب."
                        />
                    @endif
                </x-admin.table-card>

                <x-admin.table-card heading="طرق الدفع" description="توزيع المدفوعات النقدية والبطاقات على مستوى الوردية.">
                    @if (filled($this->getPaymentMethodStats()))
                        <div class="grid gap-3">
                            @foreach ($this->getPaymentMethodStats() as $row)
                                <div class="flex items-center justify-between rounded-2xl border border-gray-200/70 bg-white/70 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <x-admin.badge :tone="$row['tone']">{{ $row['label'] }}</x-admin.badge>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $this->formatNumber($row['count']) }} عملية</span>
                                        </div>
                                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $row['value'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-admin.empty-state
                            title="لا توجد مدفوعات مسجلة"
                            description="ستظهر هنا طرق الدفع وتوزيعها بمجرد تسجيل المدفوعات على الطلبات."
                        />
                    @endif
                </x-admin.table-card>
            </section>
        </div>

        <x-admin.table-card
            heading="الأصناف الأكثر بيعًا"
            description="نظرة سريعة على الأصناف الأعلى حركة خلال الوردية."
            :count="count($this->getTopSellingItems())"
        >
            @if (filled($this->getTopSellingItems()))
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                    @foreach ($this->getTopSellingItems() as $item)
                        <article class="rounded-2xl border border-gray-200/70 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">الكمية</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $this->formatNumber($item['quantity']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">المبيعات</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->formatMoney($item['sales']) }}</p>
                        </article>
                    @endforeach
                </div>
            @else
                <x-admin.empty-state
                    title="لا توجد بيانات أصناف بعد"
                    description="بمجرد وجود طلبات على الوردية ستظهر أكثر الأصناف مبيعًا هنا."
                />
            @endif
        </x-admin.table-card>

        <x-admin.table-card
            heading="جلسات الدرج ضمن الوردية"
            description="ملخص منسق لكل درج، مع انتقال مباشر إلى تفاصيل الجلسة."
            :count="$this->getDrawerSessionsTableData()->count()"
        >
            @forelse ($this->getDrawerSessionsTableData() as $session)
                @if ($loop->first)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-right text-sm dark:divide-white/10">
                            <thead class="bg-gray-50/80 dark:bg-white/5">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3">الجلسة</th>
                                    <th class="px-4 py-3">الكاشير</th>
                                    <th class="px-4 py-3">الجهاز</th>
                                    <th class="px-4 py-3">الحالة</th>
                                    <th class="px-4 py-3">المتوقع</th>
                                    <th class="px-4 py-3">الفعلي</th>
                                    <th class="px-4 py-3">الفرق</th>
                                    <th class="px-4 py-3">الطلبات</th>
                                    <th class="px-4 py-3">البداية</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @endif
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ $this->getDrawerSessionViewUrl($session) }}" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                            {{ $session->session_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $session->cashier?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $session->posDevice?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-admin.badge :tone="$this->drawerStatusTone($session->status)">{{ $session->status->label() }}</x-admin.badge>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $this->formatMoney($session->expected_balance) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $this->formatMoney($session->closing_balance) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-admin.badge :tone="$this->differenceTone($session->cash_difference)">{{ $this->formatMoney($session->cash_difference) }}</x-admin.badge>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $this->formatNumber($session->orders->count()) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $this->formatDateTime($session->started_at) }}</td>
                                </tr>
                @if ($loop->last)
                            </tbody>
                        </table>
                    </div>
                @endif
            @empty
                <x-admin.empty-state
                    title="لا توجد جلسات درج ضمن هذه الوردية"
                    description="عند فتح الأدراج وبدء البيع ستظهر الجلسات هنا مع نتائج الجرد والطلبات."
                />
            @endforelse
        </x-admin.table-card>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card
                heading="طلبات الوردية"
                description="جدول مضغوط للطلبات مع الوصول السريع إلى كل طلب."
                :count="$this->getOrdersTableData()->count()"
            >
                @forelse ($this->getOrdersTableData() as $order)
                    @if ($loop->first)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-right text-sm dark:divide-white/10">
                                <thead class="bg-gray-50/80 dark:bg-white/5">
                                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-3">الطلب</th>
                                        <th class="px-4 py-3">الكاشير</th>
                                        <th class="px-4 py-3">الحالة</th>
                                        <th class="px-4 py-3">الدفع</th>
                                        <th class="px-4 py-3">الإجمالي</th>
                                        <th class="px-4 py-3">العميل</th>
                                        <th class="px-4 py-3">الوقت</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @endif
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <a href="{{ $this->getOrderViewUrl($order) }}" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                                {{ $order->order_number }}
                                            </a>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $order->drawerSession?->session_number ?? 'بدون درج' }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $order->cashier?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <x-admin.badge :tone="$this->orderStatusTone($order->status)">{{ $order->status->label() }}</x-admin.badge>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <x-admin.badge :tone="$this->paymentStatusTone($order->payment_status)">{{ $order->payment_status->label() }}</x-admin.badge>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $this->formatMoney($order->total) }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">مدفوع {{ $this->formatMoney($order->paid_amount) }}</div>
                                        </td>
                                        <td class="px-4 py-3">{{ $order->customer_name ?: 'عميل نقدي' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $this->formatDateTime($order->created_at) }}</td>
                                    </tr>
                    @if ($loop->last)
                                </tbody>
                            </table>
                        </div>
                    @endif
                @empty
                    <x-admin.empty-state
                        title="لا توجد طلبات على الوردية"
                        description="عند بدء عمليات البيع ستظهر هنا جميع الطلبات بملخص سريع ومنظم."
                    />
                @endforelse
            </x-admin.table-card>

            <x-admin.table-card
                heading="مصروفات الوردية"
                description="المصروفات المسجلة والمعتمدة أثناء الوردية."
                :count="$this->getExpensesTableData()->count()"
            >
                @forelse ($this->getExpensesTableData() as $expense)
                    @if ($loop->first)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-right text-sm dark:divide-white/10">
                                <thead class="bg-gray-50/80 dark:bg-white/5">
                                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-3">المصروف</th>
                                        <th class="px-4 py-3">الفئة</th>
                                        <th class="px-4 py-3">الجلسة</th>
                                        <th class="px-4 py-3">المبلغ</th>
                                        <th class="px-4 py-3">الاعتماد</th>
                                        <th class="px-4 py-3">التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @endif
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-gray-900 dark:text-white">{{ $expense->expense_number }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $expense->category?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $expense->drawerSession?->session_number ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $this->formatMoney($expense->amount) }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $expense->approver?->name ?? 'بانتظار الاعتماد' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $this->formatDate($expense->expense_date) }}</td>
                                    </tr>
                    @if ($loop->last)
                                </tbody>
                            </table>
                        </div>
                    @endif
                @empty
                    <x-admin.empty-state
                        title="لا توجد مصروفات على هذه الوردية"
                        description="إذا تم تسجيل أي مصروفات أثناء الوردية ستظهر هنا مع الجلسة المرتبطة بها."
                    />
                @endforelse
            </x-admin.table-card>
        </div>
    </div>
</x-filament-panels::page>
