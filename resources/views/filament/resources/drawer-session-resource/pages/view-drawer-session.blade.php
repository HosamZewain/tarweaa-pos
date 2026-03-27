<x-filament-panels::page>
    @php($record = $this->getRecord())

    <div class="space-y-6">
        <x-admin.report-header
            eyebrow="جلسة درج"
            :title="'ملف الجلسة ' . $record->session_number"
            :description="$this->getSubtitle()"
            :meta="[
                'الكاشير: ' . ($record->cashier?->name ?? '—'),
                'الوردية: ' . ($record->shift?->shift_number ?? '—'),
                'الجهاز: ' . ($record->posDevice?->name ?? '—'),
                'الحالة: ' . $record->status->label(),
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
                        <h3 class="admin-table-card__title">البيانات التشغيلية</h3>
                        <p class="admin-table-card__description">هوية الجلسة والزمن والمسؤولين عنها.</p>
                    </div>

                    <x-admin.badge :tone="$this->drawerStatusTone($record->status)">{{ $record->status->label() }}</x-admin.badge>
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
                        <h3 class="admin-table-card__title">المؤشرات المالية</h3>
                        <p class="admin-table-card__description">تفصيل سريع لحركة النقد والمبالغ المسجلة على الجلسة.</p>
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
                <x-admin.table-card heading="إحصاءات الطلبات" description="توزيع حالات الطلب داخل الجلسة.">
                    @if (filled($this->getOrderStatusStats()))
                        <div class="grid gap-3">
                            @foreach ($this->getOrderStatusStats() as $row)
                                <div class="flex items-center justify-between rounded-2xl border border-gray-200/70 bg-white/70 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                                    <div class="flex items-center gap-2">
                                        <x-admin.badge :tone="$row['tone']">{{ $row['label'] }}</x-admin.badge>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $this->formatNumber($row['value']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-admin.empty-state
                            title="لا توجد حالات طلبات بعد"
                            description="عند إنشاء طلبات على هذه الجلسة ستظهر هنا الإحصاءات التشغيلية الخاصة بها."
                        />
                    @endif
                </x-admin.table-card>

                <x-admin.table-card heading="طرق الدفع" description="توزيع المدفوعات النقدية والبطاقات داخل الجلسة.">
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
                            title="لا توجد مدفوعات بعد"
                            description="ستظهر هنا قنوات التحصيل بمجرد بدء تسجيل عمليات الدفع على الطلبات."
                        />
                    @endif
                </x-admin.table-card>
            </section>
        </div>

        <x-admin.table-card
            heading="الأصناف الأكثر بيعًا"
            description="أكثر الأصناف حركة داخل الجلسة لتسهيل المراجعة السريعة."
            :count="count($this->getTopSellingItems())"
        >
            @if (filled($this->getTopSellingItems()))
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    @foreach ($this->getTopSellingItems() as $item)
                        <article class="rounded-2xl border border-gray-200/70 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">الكمية المباعة</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $this->formatNumber($item['quantity']) }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">قيمة المبيعات</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->formatMoney($item['sales']) }}</p>
                        </article>
                    @endforeach
                </div>
            @else
                <x-admin.empty-state
                    title="لا توجد بيانات مبيعات للأصناف"
                    description="بمجرد إضافة طلبات مدفوعة على هذه الجلسة ستظهر أكثر الأصناف مبيعًا هنا."
                />
            @endif
        </x-admin.table-card>

        <x-admin.table-card
            heading="الطلبات المرتبطة"
            description="عرض مضغوط لكل الطلبات المرتبطة بهذه الجلسة مع الوصول السريع إلى التفاصيل."
            :count="$this->getOrdersTableData()->count()"
        >
            @if ($this->getOrdersTableData()->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-right text-sm dark:divide-white/10">
                        <thead class="bg-gray-50/80 dark:bg-white/5">
                            <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">الطلب</th>
                                <th class="px-4 py-3">الحالة</th>
                                <th class="px-4 py-3">الدفع</th>
                                <th class="px-4 py-3">العميل</th>
                                <th class="px-4 py-3">الأصناف</th>
                                <th class="px-4 py-3">الإجمالي</th>
                                <th class="px-4 py-3">طرق الدفع</th>
                                <th class="px-4 py-3">الوقت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($this->getOrdersTableData() as $order)
                                <tr class="align-top">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ $this->getOrderViewUrl($order) }}" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                            {{ $order->order_number }}
                                        </a>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $order->type_label }} • {{ $order->source_label }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-admin.badge :tone="$this->orderStatusTone($order->status)">{{ $order->status->label() }}</x-admin.badge>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-admin.badge :tone="$this->paymentStatusTone($order->payment_status)">{{ $order->payment_status->label() }}</x-admin.badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $order->customer_name ?: 'عميل نقدي' }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $order->cashier?->name ?? $record->cashier?->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $this->formatNumber($order->items->sum('quantity')) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $this->formatMoney($order->total) }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">مدفوع {{ $this->formatMoney($order->paid_amount) }}</div>
                                    </td>
                                    <td class="px-4 py-3 min-w-56">
                                        <div class="space-y-1">
                                            @forelse ($order->payments as $payment)
                                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                                    {{ $payment->payment_method->label() }} {{ $this->formatMoney($payment->amount) }}
                                                </div>
                                            @empty
                                                <div class="text-xs text-gray-400">—</div>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $this->formatDateTime($order->created_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-admin.empty-state
                    title="لا توجد طلبات مرتبطة بهذه الجلسة"
                    description="بمجرد إنشاء طلبات على هذا الدرج ستظهر هنا في جدول واضح مع حالاتها وطرق دفعها."
                />
            @endif
        </x-admin.table-card>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-admin.table-card
                heading="الحركات النقدية"
                description="كل إدخال وإخراج نقدي تم تسجيله على الدرج خلال هذه الجلسة."
                :count="$this->getCashMovementsTableData()->count()"
            >
                @if ($this->getCashMovementsTableData()->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-right text-sm dark:divide-white/10">
                            <thead class="bg-gray-50/80 dark:bg-white/5">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3">النوع</th>
                                    <th class="px-4 py-3">الاتجاه</th>
                                    <th class="px-4 py-3">المبلغ</th>
                                    <th class="px-4 py-3">المرجع</th>
                                    <th class="px-4 py-3">بواسطة</th>
                                    <th class="px-4 py-3">التوقيت</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($this->getCashMovementsTableData() as $movement)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $movement->type->label() }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $movement->direction->label() }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-gray-900 dark:text-white">{{ $this->formatMoney($movement->amount) }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $movement->reference_type ?: '—' }}
                                            @if ($movement->reference_id)
                                                <div>#{{ $movement->reference_id }}</div>
                                            @endif
                                            @if (filled($movement->notes))
                                                <div class="mt-1">{{ $movement->notes }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $movement->performer?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $this->formatDateTime($movement->created_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty-state
                        title="لا توجد حركات نقدية على الجلسة"
                        description="عند تسجيل فتح أو مبيعات نقدية أو سحوبات أو استرجاعات ستظهر هنا بترتيب زمني."
                    />
                @endif
            </x-admin.table-card>

            <x-admin.table-card
                heading="مصروفات الجلسة"
                description="المصروفات التي تم تسجيلها وربطها بهذه الجلسة."
                :count="$this->getExpensesTableData()->count()"
            >
                @if ($this->getExpensesTableData()->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-right text-sm dark:divide-white/10">
                            <thead class="bg-gray-50/80 dark:bg-white/5">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3">المصروف</th>
                                    <th class="px-4 py-3">الفئة</th>
                                    <th class="px-4 py-3">المبلغ</th>
                                    <th class="px-4 py-3">طريقة الدفع</th>
                                    <th class="px-4 py-3">الاعتماد</th>
                                    <th class="px-4 py-3">الوصف</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($this->getExpensesTableData() as $expense)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-gray-900 dark:text-white">{{ $expense->expense_number }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $expense->category?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $this->formatMoney($expense->amount) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $expense->payment_method ?: '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $expense->approver?->name ?? 'بانتظار الاعتماد' }}
                                            @if ($expense->approved_at)
                                                <div class="mt-1">{{ $this->formatDateTime($expense->approved_at) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $expense->description ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty-state
                        title="لا توجد مصروفات مرتبطة بهذه الجلسة"
                        description="إذا تم تسجيل أي مصروف وربطه بالدرج فسيظهر هنا مع حالة الاعتماد."
                    />
                @endif
            </x-admin.table-card>
        </div>
    </div>
</x-filament-panels::page>
