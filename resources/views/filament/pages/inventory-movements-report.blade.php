<x-filament-panels::page>
    @php
        $movementRows = collect($reportData['movements'] ?? []);
        $inboundTypes = ['purchase', 'adjustment_add', 'return'];
        $outboundTypes = ['sale', 'waste', 'adjustment_sub'];

        $totalInbound = $movementRows->sum(function (array $row) use ($inboundTypes) {
            return collect($row['movements'])
                ->filter(fn (array $value, string $type): bool => in_array($type, $inboundTypes, true))
                ->sum(fn (array $value): float => (float) ($value['quantity'] ?? 0));
        });

        $totalOutbound = $movementRows->sum(function (array $row) use ($outboundTypes) {
            return collect($row['movements'])
                ->filter(fn (array $value, string $type): bool => in_array($type, $outboundTypes, true))
                ->sum(fn (array $value): float => (float) ($value['quantity'] ?? 0));
        });

        $totalInboundCost = $movementRows->sum(function (array $row) use ($inboundTypes) {
            return collect($row['movements'])
                ->filter(fn (array $value, string $type): bool => in_array($type, $inboundTypes, true))
                ->sum(fn (array $value): float => (float) ($value['cost'] ?? 0));
        });
    @endphp

    <div class="admin-page-shell">
        <x-admin.report-header
            title="تقرير حركة المخزون"
            description="مراجعة حركة الوارد والصادر وتكلفة الإمداد لكل مادة مخزنية خلال الفترة المحددة."
            :from="$date_from"
            :to="$date_to"
            :meta="['تجميع حسب المادة', 'يدعم المراجعة التشغيلية السريعة']"
        />

        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">فلاتر التقرير</h3>
                    <p class="admin-filter-card__description">حدد الفترة الزمنية لمراجعة الوارد والصادر وحركة المواد المخزنية.</p>
                </div>

                <x-admin.badge tone="success">متابعة المخزون</x-admin.badge>
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
                    title="مواد بحركة"
                    :value="number_format($reportData['total_items'])"
                    hint="مواد لديها نشاط خلال الفترة"
                    tone="primary"
                />

                <x-admin.metric-card
                    title="إجمالي الوارد"
                    :value="number_format($totalInbound, 3)"
                    hint="مجموع الكميات الداخلة"
                    tone="success"
                />

                <x-admin.metric-card
                    title="إجمالي الصادر"
                    :value="number_format($totalOutbound, 3)"
                    hint="مجموع الكميات الخارجة"
                    tone="danger"
                />

                <x-admin.metric-card
                    title="تكلفة الوارد"
                    :value="number_format($totalInboundCost, 2) . ' ج.م'"
                    hint="تكلفة الإمداد خلال الفترة"
                    tone="info"
                />
            </div>

            <x-admin.table-card
                heading="تفاصيل حركة المخزون"
                description="تفصيل لكل مادة مخزنية مع الوارد والصادر وتكلفة الإمداد ضمن الفترة."
                :count="$movementRows->count()"
            >
                @if ($movementRows->isEmpty())
                    <x-admin.empty-state title="لا توجد حركات مخزون في هذه الفترة" />
                @else
                    <div class="admin-table-scroll">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>المادة</th>
                                    <th>التصنيف</th>
                                    <th>الوحدة</th>
                                    <th>الوارد</th>
                                    <th>الصادر</th>
                                    <th>تكلفة الوارد</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData['movements'] as $row)
                                    @php
                                        $item = $row['item'];
                                        $movements = collect($row['movements']);
                                        $inbound = $movements
                                            ->filter(fn (array $value, string $type): bool => in_array($type, $inboundTypes, true))
                                            ->sum(fn (array $value): float => (float) ($value['quantity'] ?? 0));
                                        $inboundCost = $movements
                                            ->filter(fn (array $value, string $type): bool => in_array($type, $inboundTypes, true))
                                            ->sum(fn (array $value): float => (float) ($value['cost'] ?? 0));
                                        $outbound = $movements
                                            ->filter(fn (array $value, string $type): bool => in_array($type, $outboundTypes, true))
                                            ->sum(fn (array $value): float => (float) ($value['quantity'] ?? 0));
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $item->name ?? '—' }}</div>
                                            <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item->sku ?? '—' }}</div>
                                        </td>
                                        <td>{{ $item->category ?? '—' }}</td>
                                        <td>{{ $item->unit ?? '—' }}</td>
                                        <td class="font-semibold text-success-600 dark:text-success-400">
                                            {{ $inbound > 0 ? '+' . number_format($inbound, 3) : '—' }}
                                        </td>
                                        <td class="font-semibold text-danger-600 dark:text-danger-400">
                                            {{ $outbound > 0 ? '-' . number_format($outbound, 3) : '—' }}
                                        </td>
                                        <td>{{ $inboundCost > 0 ? number_format($inboundCost, 2) . ' ج.م' : '—' }}</td>
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
