<x-filament-widgets::widget class="fi-dashboard-hero-widget">
    <section class="admin-page-hero w-full min-w-0">
        <div class="admin-page-hero__grid">
            <div class="space-y-5 min-w-0">
                <div>
                    <span class="admin-page-hero__eyebrow">لوحة متابعة الإدارة</span>
                    <h2 class="admin-page-hero__title">اختصارات الإدارة ونظرة البداية</h2>
                    <p class="admin-page-hero__description">
                        نقطة بداية سريعة للوصول إلى أهم الشاشات الإدارية، مع لمحة خفيفة عن الحالة التشغيلية الحالية بدون تكرار بطاقات المؤشرات.
                    </p>
                </div>

                <div class="admin-page-hero__meta">
                    <span class="admin-page-hero__meta-item">اليوم {{ now()->format('Y/m/d') }}</span>
                    <span class="admin-page-hero__meta-item">
                        {{ $activeShift ? 'الوردية #' . $activeShift->shift_number . ' مفتوحة' : 'لا توجد وردية مفتوحة حاليًا' }}
                    </span>
                    <span class="admin-page-hero__meta-item">
                        {{ $openDrawers === 0 ? 'لا توجد أدراج مفتوحة' : number_format($openDrawers) . ' درج/أدراج مفتوحة' }}
                    </span>
                </div>

                @if ($links->isNotEmpty())
                    <div class="admin-quick-links">
                        @foreach ($links as $link)
                            <a href="{{ $link['url'] }}" class="admin-quick-link">
                                <div class="min-w-0">
                                    <div class="admin-quick-link__title">{{ $link['label'] }}</div>
                                    <p class="admin-quick-link__description">{{ $link['description'] }}</p>
                                </div>

                                <x-admin.badge :tone="$link['tone']">فتح</x-admin.badge>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="space-y-3 min-w-0">
                @foreach ($focusItems as $item)
                    <article class="rounded-2xl border border-gray-200/70 bg-white/70 p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                                <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $item['value'] }}</p>
                                <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $item['description'] }}</p>
                            </div>

                            <x-admin.badge :tone="$item['tone']">{{ $item['label'] }}</x-admin.badge>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-filament-widgets::widget>
