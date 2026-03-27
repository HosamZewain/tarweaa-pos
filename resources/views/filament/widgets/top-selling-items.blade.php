<x-filament-widgets::widget class="fi-top-selling-items-widget">
    <x-admin.table-card
        heading="الأصناف الأعلى مبيعًا اليوم"
        description="ترتيب مباشر للأصناف الأكثر طلبًا مع مقارنة بصرية للكمية والإيراد."
        :count="$items->count()"
        class="w-full min-w-0"
    >
        @if ($items->isEmpty())
            <x-admin.empty-state
                title="لا توجد مبيعات اليوم بعد"
                description="بمجرد تسجيل أول الطلبات اليوم ستظهر قائمة الأصناف الأعلى مبيعًا هنا."
            />
        @else
            <div class="admin-ranked-list">
                @foreach ($items as $i => $item)
                    @php
                        $progress = min(100, round(($item->total_qty / $maxQuantity) * 100));
                    @endphp

                    <div class="admin-ranked-list__row">
                        <div class="admin-ranked-list__main">
                            <div class="admin-ranked-list__index">{{ $i + 1 }}</div>

                            <div class="min-w-0">
                                <div class="admin-ranked-list__title">{{ $item->item_name }}</div>
                                <p class="admin-ranked-list__meta">
                                    {{ number_format($item->total_qty) }} طلب/قطعة مباعة اليوم
                                </p>
                            </div>
                        </div>

                        <div class="admin-ranked-list__stats">
                            <div class="admin-ranked-list__statline">
                                <span class="text-gray-500 dark:text-gray-400">الإيراد</span>
                                <span class="font-semibold text-success-600 dark:text-success-400">
                                    {{ number_format($item->total_rev, 2) }} ج.م
                                </span>
                            </div>

                            <div class="admin-progress" aria-hidden="true">
                                <span style="width: {{ $progress }}%"></span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-admin.table-card>
</x-filament-widgets::widget>
