<x-filament-widgets::widget>
    <section class="admin-page-shell">
        <div class="admin-filter-card">
            <div class="admin-filter-card__header">
                <div>
                    <h3 class="admin-filter-card__title">المتصلون خلال آخر ساعة</h3>
                    <p class="admin-filter-card__description">مؤشر سريع على من كان نشطًا مؤخرًا، مع آخر نشاط محسوب بالدقائق.</p>
                </div>
            </div>

            <div class="admin-two-column-grid">
                <div class="admin-online-summary-grid">
                    <article class="admin-metric-card admin-metric-card--success">
                        <p class="admin-metric-card__label">إجمالي المتصلين</p>
                        <p class="admin-metric-card__value">{{ number_format($summary['total']) }}</p>
                        <p class="admin-metric-card__hint">آخر 60 دقيقة</p>
                    </article>

                    <article class="admin-metric-card admin-metric-card--info">
                        <p class="admin-metric-card__label">نشطون الآن</p>
                        <p class="admin-metric-card__value">{{ number_format($summary['recently_active']) }}</p>
                        <p class="admin-metric-card__hint">آخر 5 دقائق</p>
                    </article>

                    <article class="admin-metric-card admin-metric-card--warning">
                        <p class="admin-metric-card__label">إداريون متصلون</p>
                        <p class="admin-metric-card__value">{{ number_format($summary['privileged']) }}</p>
                        <p class="admin-metric-card__hint">Admin / Manager / Owner</p>
                    </article>

                    <article class="admin-metric-card admin-metric-card--neutral">
                        <p class="admin-metric-card__label">تشغيل متصل</p>
                        <p class="admin-metric-card__value">{{ number_format($summary['operational']) }}</p>
                        <p class="admin-metric-card__hint">Cashier / Counter / Kitchen / Inventory</p>
                    </article>
                </div>

                <div class="admin-table-card">
                    <div class="admin-table-card__header">
                        <div>
                            <h4 class="admin-table-card__title">آخر المستخدمين نشاطًا</h4>
                            <p class="admin-table-card__description">مرتبة من الأحدث إلى الأقدم داخل نافذة الساعة الأخيرة.</p>
                        </div>
                    </div>

                    @if ($onlineUsers->isEmpty())
                        <div class="admin-empty-state">
                            لا يوجد مستخدمون متصلون خلال آخر ساعة.
                        </div>
                    @else
                        <div class="admin-table-card__body">
                            <div class="admin-ranked-list">
                                @foreach ($onlineUsers as $user)
                                    <article class="admin-ranked-list__row">
                                        <div class="admin-ranked-list__main">
                                            <div class="admin-ranked-list__index">
                                                {{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}
                                            </div>

                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="admin-ranked-list__title">{{ $user['name'] }}</p>
                                                    <x-admin.badge :tone="$user['status_tone']">{{ $user['status_label'] }}</x-admin.badge>
                                                </div>
                                                <p class="admin-ranked-list__meta">{{ $user['role_label'] }}</p>
                                            </div>
                                        </div>

                                        <div class="admin-ranked-list__stats">
                                            <div class="admin-ranked-list__statline">
                                                <span class="text-gray-500 dark:text-gray-400">آخر نشاط</span>
                                                <span class="font-semibold text-gray-900 dark:text-white">{{ $user['last_activity_label'] }}</span>
                                            </div>
                                            <div class="admin-ranked-list__statline">
                                                <span class="text-gray-500 dark:text-gray-400">الوقت</span>
                                                <span class="text-gray-700 dark:text-gray-200">{{ $user['last_activity_at']->format('Y-m-d H:i') }}</span>
                                            </div>
                                            <div class="admin-ranked-list__statline">
                                                <span class="text-gray-500 dark:text-gray-400">السياق</span>
                                                <span class="admin-online-context">
                                                    {{ $user['drawer_session_number'] ? 'جلسة درج ' . $user['drawer_session_number'] : 'نشاط ويب/إدارة' }}
                                                </span>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-filament-widgets::widget>
