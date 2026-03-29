@extends('layouts.app')

@php
    $laneLabel = match ($lane) {
        'all' => 'كل الطلبات',
        'odd' => 'الفردية',
        'even' => 'الزوجية',
        default => $lane,
    };
@endphp

@section('title', "شاشة التسليم والاستلام {$laneLabel} — Tarweaa")

@section('styles')
<style>
    .theme-toggle-btn {
        min-width: 110px;
        font-weight: 700;
    }
    .counter-shell {
        display: flex;
        flex-direction: column;
        height: 100vh;
        color: var(--text-primary);
        background:
            radial-gradient(circle at top right, rgba(99, 102, 241, 0.12), transparent 35%),
            linear-gradient(180deg, #11141c 0%, #0d1016 100%);
    }
    .counter-shell.theme-light {
        --bg-primary: #f4f7fb;
        --bg-secondary: #ffffff;
        --bg-card: #ffffff;
        --bg-card-hover: #eef2ff;
        --bg-input: #ffffff;
        --border: #d7deea;
        --text-primary: #111827;
        --text-secondary: #4b5563;
        --text-muted: #6b7280;
        --shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
        background:
            radial-gradient(circle at top right, rgba(14, 165, 233, 0.14), transparent 32%),
            linear-gradient(180deg, #fbfdff 0%, #edf3fb 100%);
    }
    .counter-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: rgba(19, 23, 31, 0.92);
        backdrop-filter: blur(12px);
    }
    .counter-shell.theme-light .counter-topbar {
        background: rgba(255, 255, 255, 0.94);
    }
    .counter-title {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }
    .counter-lane-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.85rem;
        background: rgba(99, 102, 241, 0.14);
        border: 1px solid rgba(99, 102, 241, 0.35);
        border-radius: 999px;
        color: #c7d2fe;
        font-size: 0.85rem;
        font-weight: 800;
    }
    .counter-meta {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .counter-toolbar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .counter-keypad-box {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: rgba(36, 40, 54, 0.96);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: 0.45rem 0.8rem;
        min-width: 340px;
    }
    .counter-shell.theme-light .counter-keypad-box {
        background: #ffffff;
    }
    .counter-keypad-label {
        color: var(--text-secondary);
        font-size: 0.8rem;
        white-space: nowrap;
    }
    .counter-keypad-input {
        flex: 1;
        border: none;
        background: transparent;
        color: var(--text-primary);
        font-size: 1.15rem;
        font-weight: 800;
        text-align: center;
        outline: none;
        letter-spacing: 0.06em;
    }
    .counter-keypad-hint {
        color: var(--text-secondary);
        font-size: 0.75rem;
        white-space: nowrap;
    }
    .counter-keypad-status {
        color: var(--text-secondary);
        font-size: 0.8rem;
        min-width: 120px;
        font-weight: 700;
    }
    .counter-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        padding: 1rem 1.5rem 0;
    }
    .counter-stat {
        background: rgba(30, 35, 47, 0.94);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 1rem 1.1rem;
        box-shadow: var(--shadow);
    }
    .counter-shell.theme-light .counter-stat {
        background: rgba(255, 255, 255, 0.96);
    }
    .counter-stat__label {
        color: var(--text-secondary);
        font-size: 0.85rem;
        margin-bottom: 0.45rem;
    }
    .counter-stat__value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }
    .counter-stat.ready .counter-stat__value { color: var(--success); }
    .counter-stat.preparing .counter-stat__value { color: var(--warning); }
    .counter-stat.confirmed .counter-stat__value { color: var(--accent-hover); }
    .counter-board {
        flex: 1;
        overflow: auto;
        padding: 1rem 1.5rem 1.5rem;
    }
    .counter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1rem;
        align-content: start;
    }
    .counter-card {
        position: relative;
        display: flex;
        flex-direction: column;
        min-height: 280px;
        background: linear-gradient(180deg, rgba(31, 36, 49, 0.98), rgba(18, 22, 30, 0.98));
        border: 2px solid var(--border);
        border-radius: 24px;
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .counter-shell.theme-light .counter-card {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(244, 247, 251, 0.98));
    }
    .counter-shell.theme-light .counter-lane-badge {
        color: #4338ca;
        background: rgba(79, 70, 229, 0.12);
    }
    .counter-card.ready {
        border-color: rgba(34, 197, 94, 0.55);
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.16), var(--shadow);
    }
    .counter-card.selected-by-keypad {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18), var(--shadow);
        transform: translateY(-2px);
    }
    .counter-card.preparing {
        border-color: rgba(245, 158, 11, 0.45);
    }
    .counter-card.confirmed {
        border-color: rgba(99, 102, 241, 0.35);
    }
    .counter-card__header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.1rem 1.1rem 0.8rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        background: rgba(255, 255, 255, 0.02);
    }
    .counter-shell.theme-light .counter-card__header {
        border-bottom-color: rgba(15, 23, 42, 0.08);
        background: rgba(79, 70, 229, 0.04);
    }
    .counter-number {
        font-size: 3.3rem;
        font-weight: 900;
        line-height: 1;
        color: #ffffff;
        letter-spacing: 0.02em;
    }
    .counter-shell.theme-light .counter-number {
        color: #111827;
    }
    .counter-order-ref {
        margin-top: 0.35rem;
        color: var(--text-secondary);
        font-size: 0.88rem;
        font-weight: 700;
    }
    .counter-time {
        margin-top: 0.55rem;
        color: var(--text-secondary);
        font-size: 0.9rem;
        font-weight: 600;
    }
    .counter-shortcut {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.5rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-secondary);
    }
    .counter-shortcut__value {
        color: var(--accent);
        font-size: 1rem;
    }
    .counter-priority-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.55rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        background: rgba(245, 158, 11, 0.14);
        border: 1px solid rgba(245, 158, 11, 0.32);
        color: #fcd34d;
        font-size: 0.76rem;
        font-weight: 800;
    }
    .counter-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 92px;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 800;
    }
    .counter-status.ready {
        background: rgba(34, 197, 94, 0.16);
        color: #86efac;
        border: 1px solid rgba(34, 197, 94, 0.3);
    }
    .counter-status.preparing {
        background: rgba(245, 158, 11, 0.14);
        color: #fcd34d;
        border: 1px solid rgba(245, 158, 11, 0.26);
    }
    .counter-status.confirmed {
        background: rgba(99, 102, 241, 0.14);
        color: #c7d2fe;
        border: 1px solid rgba(99, 102, 241, 0.28);
    }
    .counter-card__body {
        display: grid;
        gap: 0.9rem;
        padding: 1rem 1.1rem 1.1rem;
        flex: 1;
    }
    .counter-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .counter-meta-cell {
        padding: 0.7rem 0.8rem;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 14px;
    }
    .counter-shell.theme-light .counter-meta-cell {
        background: rgba(15, 23, 42, 0.03);
        border-color: rgba(15, 23, 42, 0.06);
    }
    .counter-meta-cell__label {
        color: var(--text-secondary);
        font-size: 0.78rem;
        margin-bottom: 0.2rem;
    }
    .counter-meta-cell__value {
        font-size: 0.95rem;
        font-weight: 700;
    }
    .counter-items {
        display: grid;
        gap: 0.45rem;
    }
    .counter-items__title {
        color: var(--text-secondary);
        font-size: 0.78rem;
        font-weight: 700;
    }
    .counter-item-chip-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }
    .counter-item-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.45rem 0.7rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.05);
        color: #e5e7eb;
        border: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 0.82rem;
        font-weight: 700;
    }
    .counter-shell.theme-light .counter-item-chip {
        background: rgba(15, 23, 42, 0.04);
        color: #1f2937;
        border-color: rgba(15, 23, 42, 0.08);
    }
    .counter-shell.theme-light .counter-priority-badge {
        color: #b45309;
        background: rgba(245, 158, 11, 0.18);
    }
    .counter-item-chip__qty {
        color: var(--accent-hover);
        font-weight: 800;
    }
    .counter-action {
        margin-top: auto;
    }
    .counter-action__hint {
        text-align: center;
        color: var(--text-secondary);
        font-size: 0.83rem;
        font-weight: 700;
        padding: 0.9rem 1rem 0;
    }
    .counter-empty {
        min-height: calc(100vh - 240px);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 0.6rem;
        color: var(--text-secondary);
        text-align: center;
    }
    .counter-empty__icon {
        font-size: 4rem;
    }
    .counter-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 280px;
    }
    @media (max-width: 1100px) {
        .counter-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 760px) {
        .counter-topbar {
            flex-direction: column;
            align-items: stretch;
        }
        .counter-keypad-box {
            min-width: 100%;
        }
        .counter-stats {
            grid-template-columns: 1fr;
        }
        .counter-grid {
            grid-template-columns: 1fr;
        }
        .counter-meta-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="counter-shell">
    <div class="counter-topbar">
        <div class="counter-title">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold">شاشة التسليم والاستلام</h1>
                <span class="counter-lane-badge">شاشة {{ $laneLabel }}</span>
            </div>
            <p class="text-sm text-muted">الطلبات المدفوعة النشطة لهذا الكاونتر تظهر هنا حتى تسليمها النهائي</p>
        </div>

        <div class="counter-meta">
            <div class="counter-toolbar">
                <div class="counter-keypad-box">
                    <span class="counter-keypad-label">رقم الطلب</span>
                    <input
                        id="counter-order-input"
                        class="counter-keypad-input"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="مثال: 15"
                        aria-label="رقم الطلب"
                    >
                    <span class="counter-keypad-hint">اضغط Enter للتسليم</span>
                </div>
                <div id="counter-keypad-status" class="counter-keypad-status">بانتظار الإدخال</div>
            </div>
            <div id="counter-connection-status" class="flex items-center gap-2 text-xs text-success">
                <span class="w-2 h-2 rounded-full bg-success"></span>
                متصل
            </div>
            <button id="counter-theme-toggle" type="button" class="btn btn-sm btn-ghost theme-toggle-btn">☀️ فاتح</button>
            <button class="btn btn-sm btn-secondary" onclick="fetchCounterOrders(true)">🔄 تحديث</button>
            <button id="counter-pos-link" class="btn btn-sm btn-ghost hidden" onclick="location.href='/pos'">نقطة البيع</button>
        </div>
    </div>

    <div class="counter-stats">
        <div class="counter-stat">
            <div class="counter-stat__label">إجمالي الطلبات</div>
            <div id="counter-total-stat" class="counter-stat__value">0</div>
        </div>
        <div class="counter-stat ready">
            <div class="counter-stat__label">جاهز للتسليم</div>
            <div id="counter-ready-stat" class="counter-stat__value">0</div>
        </div>
        <div class="counter-stat preparing">
            <div class="counter-stat__label">قيد التحضير</div>
            <div id="counter-preparing-stat" class="counter-stat__value">0</div>
        </div>
        <div class="counter-stat confirmed">
            <div class="counter-stat__label">بانتظار المطبخ</div>
            <div id="counter-confirmed-stat" class="counter-stat__value">0</div>
        </div>
    </div>

    <div class="counter-board">
        <div id="counter-board" class="counter-grid">
            <div class="counter-loading">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const OPS_THEME_KEY = 'tarweaa_ops_surface_theme';
    const COUNTER_LANE = @json($lane);
    const COUNTER_REDIRECT = COUNTER_LANE === 'all' ? '/counter' : `/counter/${COUNTER_LANE}`;
    let counterOrders = [];
    let handoverBusyOrderId = null;
    let counterRefreshTimer = null;
    let selectedCounterOrderId = null;

    const counterScreen = document.querySelector('.counter-shell');
    const counterOrderInput = document.getElementById('counter-order-input');
    const counterKeypadStatus = document.getElementById('counter-keypad-status');
    const counterThemeToggle = document.getElementById('counter-theme-toggle');

    function applyCounterTheme(theme) {
        const isLight = theme === 'light';

        counterScreen.classList.toggle('theme-light', isLight);
        document.body.style.background = isLight ? '#edf3fb' : '#0f1117';
        document.body.style.color = isLight ? '#111827' : '#e4e6eb';
        counterThemeToggle.textContent = isLight ? '🌙 داكن' : '☀️ فاتح';
        counterThemeToggle.setAttribute('aria-pressed', isLight ? 'true' : 'false');
    }

    function toggleCounterTheme() {
        const nextTheme = counterScreen.classList.contains('theme-light') ? 'dark' : 'light';
        localStorage.setItem(OPS_THEME_KEY, nextTheme);
        applyCounterTheme(nextTheme);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function formatDateTime(value) {
        if (!value) return '—';

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat('ar-EG', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
        }).format(date);
    }

    function statusClass(status) {
        switch (status) {
            case 'ready': return 'ready';
            case 'preparing': return 'preparing';
            default: return 'confirmed';
        }
    }

    function timeLabel(order) {
        const anchor = order.ready_at || order.confirmed_at || order.created_at;
        return getTimeAgo(anchor);
    }

    function getTimeAgo(dateStr) {
        if (!dateStr) return 'الآن';

        const now = new Date();
        const past = new Date(dateStr);
        const diffMs = now - past;
        const diffMins = Math.max(0, Math.floor(diffMs / 60000));

        if (diffMins < 1) return 'الآن';
        if (diffMins < 60) return `منذ ${diffMins} د`;

        const hours = Math.floor(diffMins / 60);
        return `منذ ${hours} س`;
    }

    function getCustomerName(order) {
        return order.customer_name || order.customer?.name || 'عميل بدون اسم';
    }

    function getCounterShortcutNumber(order) {
        if (order?.counter_number !== null && order?.counter_number !== undefined) {
            return String(order.counter_number);
        }

        const suffix = String(order?.order_number || '').split('-').pop() || '';
        const numeric = parseInt(suffix, 10);

        return Number.isNaN(numeric) ? suffix : String(numeric);
    }

    function findCounterOrderByTypedNumber(value) {
        const normalized = String(value || '').trim();

        if (!normalized) {
            return null;
        }

        return counterOrders.find(order => getCounterShortcutNumber(order) === normalized) || null;
    }

    function setCounterStatusMessage(message, tone = 'muted') {
        counterKeypadStatus.textContent = message;
        counterKeypadStatus.style.color = tone === 'success'
            ? 'var(--success)'
            : tone === 'danger'
                ? 'var(--danger)'
                : tone === 'warning'
                    ? 'var(--warning)'
                    : 'var(--text-secondary)';
    }

    function focusCounterInput() {
        counterOrderInput?.focus();
        counterOrderInput?.select();
    }

    function clearCounterSelection() {
        selectedCounterOrderId = null;
        counterOrderInput.value = '';
        setCounterStatusMessage('بانتظار الإدخال');
        renderCounterOrders();
        focusCounterInput();
    }

    function syncSelectedCounterOrderFromInput() {
        const match = findCounterOrderByTypedNumber(counterOrderInput.value);

        if (!counterOrderInput.value.trim()) {
            selectedCounterOrderId = null;
            setCounterStatusMessage('بانتظار الإدخال');
            return;
        }

        if (match) {
            selectedCounterOrderId = match.id;
            if (match.status === 'ready') {
                setCounterStatusMessage(`تم تحديد الطلب #${getCounterShortcutNumber(match)}`, 'success');
            } else {
                setCounterStatusMessage(`الطلب #${getCounterShortcutNumber(match)} ليس جاهزًا بعد`, 'warning');
            }
            return;
        }

        selectedCounterOrderId = null;
        setCounterStatusMessage('رقم الطلب غير موجود', 'danger');
    }

    function getCounterCardAction(order) {
        if (order.status === 'ready') {
            return `
                <button class="btn btn-success btn-lg btn-block counter-action" ${handoverBusyOrderId === order.id ? 'disabled' : ''} onclick="handoverCounterOrder(${order.id})">
                    ${handoverBusyOrderId === order.id ? '... جاري التسليم' : 'تم التسليم'}
                </button>
            `;
        }

        return `
            <div class="counter-action__hint">
                ${order.status === 'preparing' ? 'الطلب ما زال في المطبخ' : 'تم الدفع وينتظر بدء التحضير'}
            </div>
        `;
    }

    function renderCounterOrders() {
        const board = document.getElementById('counter-board');

        if (!counterOrders.length) {
            board.innerHTML = `
                <div class="counter-empty" style="grid-column: 1 / -1;">
                    <div class="counter-empty__icon">🧾</div>
                    <div class="text-xl font-bold">لا توجد طلبات نشطة في شاشة {{ $laneLabel }}</div>
                    <div class="text-sm">ستظهر الطلبات المدفوعة هنا تلقائيًا حتى يتم تسليمها</div>
                </div>
            `;
            return;
        }

        board.innerHTML = counterOrders.map((order, index) => `
            <div class="counter-card ${statusClass(order.status)} ${selectedCounterOrderId === order.id ? 'selected-by-keypad' : ''}" id="counter-order-${order.id}">
                <div class="counter-card__header">
                    <div>
                        <div class="counter-number">#${getCounterShortcutNumber(order)}</div>
                        <div class="counter-order-ref">${escapeHtml(order.order_number)}</div>
                        <div class="counter-time">${timeLabel(order)}</div>
                        ${index === 0 ? `<div class="counter-priority-badge">⏳ الأقدم انتظارًا</div>` : ''}
                        <div class="counter-shortcut">
                            <span>رقم لوحة الأرقام</span>
                            <span class="counter-shortcut__value">${getCounterShortcutNumber(order)}</span>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="counter-status ${statusClass(order.status)}">${escapeHtml(order.status === 'ready' ? 'جاهز للتسليم' : (order.status_label || order.status))}</span>
                        <span class="text-xs text-muted font-bold">${escapeHtml(order.type_label || order.type)}</span>
                    </div>
                </div>
                <div class="counter-card__body">
                    <div class="counter-meta-grid">
                        <div class="counter-meta-cell">
                            <div class="counter-meta-cell__label">العميل</div>
                            <div class="counter-meta-cell__value">${escapeHtml(getCustomerName(order))}</div>
                        </div>
                        <div class="counter-meta-cell">
                            <div class="counter-meta-cell__label">وقت الدفع / التأكيد</div>
                            <div class="counter-meta-cell__value">${escapeHtml(formatDateTime(order.confirmed_at || order.created_at))}</div>
                        </div>
                    </div>

                    <div class="counter-items">
                        <div class="counter-items__title">ملخص الطلب</div>
                        <div class="counter-item-chip-wrap">
                            ${order.items.map(item => `
                                <span class="counter-item-chip">
                                    <span>${escapeHtml(item.item_name)}${item.variant_name ? ` (${escapeHtml(item.variant_name)})` : ''}</span>
                                    <span class="counter-item-chip__qty">×${escapeHtml(String(item.quantity))}</span>
                                </span>
                            `).join('')}
                        </div>
                    </div>

                    ${getCounterCardAction(order)}
                </div>
            </div>
        `).join('');

        if (selectedCounterOrderId) {
            document.getElementById(`counter-order-${selectedCounterOrderId}`)?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        }
    }

    function updateCounterStats(stats = {}) {
        document.getElementById('counter-total-stat').textContent = stats.total ?? 0;
        document.getElementById('counter-ready-stat').textContent = stats.ready ?? 0;
        document.getElementById('counter-preparing-stat').textContent = stats.preparing ?? 0;
        document.getElementById('counter-confirmed-stat').textContent = stats.confirmed ?? 0;
    }

    async function fetchCounterOrders(showFeedback = false) {
        try {
            const res = await api(`/counter/orders/${COUNTER_LANE}`);

            if (!res?.success) {
                return;
            }

            document.getElementById('counter-connection-status').innerHTML = '<span class="w-2 h-2 rounded-full bg-success"></span> متصل';
            counterOrders = res.data.orders || [];
            syncSelectedCounterOrderFromInput();
            updateCounterStats(res.data.stats || {});
            renderCounterOrders();

            if (showFeedback) {
                showToast('تم تحديث شاشة التسليم');
            }
        } catch (err) {
            console.error('Counter fetch failed:', err);
            document.getElementById('counter-connection-status').innerHTML = '<span class="w-2 h-2 rounded-full bg-danger"></span> غير متصل';
            showToast(err.message || 'تعذر تحميل طلبات شاشة التسليم', 'error');
        }
    }

    async function handoverCounterOrder(orderId) {
        handoverBusyOrderId = orderId;
        renderCounterOrders();

        try {
            const res = await api(`/counter/orders/${orderId}/handover`, {
                method: 'POST',
            });

            if (res?.success) {
                clearCounterSelection();
                showToast('تم تسليم الطلب بنجاح');
                await fetchCounterOrders(false);
            }
        } catch (err) {
            showToast(err.message || 'تعذر تسليم الطلب', 'error');
        } finally {
            handoverBusyOrderId = null;
            renderCounterOrders();
        }
    }

    async function submitCounterShortcut() {
        const match = findCounterOrderByTypedNumber(counterOrderInput.value);

        if (!match) {
            setCounterStatusMessage('لا يوجد طلب بهذا الرقم', 'danger');
            showToast('رقم الطلب غير موجود في شاشة الكاونتر الحالية', 'error');
            return;
        }

        selectedCounterOrderId = match.id;
        renderCounterOrders();

        if (match.status !== 'ready') {
            setCounterStatusMessage(`الطلب #${getCounterShortcutNumber(match)} ليس جاهزًا للتسليم`, 'warning');
            showToast('الطلب لم يصبح جاهزًا للتسليم بعد', 'error');
            return;
        }

        setCounterStatusMessage(`جارٍ تسليم الطلب #${getCounterShortcutNumber(match)}...`, 'warning');
        await handoverCounterOrder(match.id);
    }

    function startCounterPolling() {
        if (counterRefreshTimer) {
            clearInterval(counterRefreshTimer);
        }

        counterRefreshTimer = setInterval(() => fetchCounterOrders(false), 10000);
    }

    if (!requireAuth(COUNTER_REDIRECT)) {
        throw new Error('Unauthenticated');
    }

    if (!canAccessCounterSurface()) {
        showToast('ليس لديك صلاحية لعرض شاشة التسليم والاستلام', 'error');
        setTimeout(() => redirectToAuthorizedHome(), 800);
        throw new Error('Forbidden');
    }

    if (canAccessPosSurface()) {
        document.getElementById('counter-pos-link')?.classList.remove('hidden');
    }
    applyCounterTheme(localStorage.getItem(OPS_THEME_KEY) || 'dark');
    counterThemeToggle?.addEventListener('click', toggleCounterTheme);

    counterOrderInput?.addEventListener('input', () => {
        counterOrderInput.value = counterOrderInput.value.replace(/[^\d]/g, '');
        syncSelectedCounterOrderFromInput();
        renderCounterOrders();
    });

    counterOrderInput?.addEventListener('keydown', async (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            await submitCounterShortcut();
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            clearCounterSelection();
        }
    });

    document.addEventListener('keydown', async (event) => {
        const target = event.target;
        const isEditable = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target?.isContentEditable;

        if (!isEditable) {
            if (/^\d$/.test(event.key)) {
                counterOrderInput.value = `${counterOrderInput.value}${event.key}`;
                syncSelectedCounterOrderFromInput();
                renderCounterOrders();
                focusCounterInput();
                return;
            }

            if (event.key === 'Backspace') {
                counterOrderInput.value = counterOrderInput.value.slice(0, -1);
                syncSelectedCounterOrderFromInput();
                renderCounterOrders();
                focusCounterInput();
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                await submitCounterShortcut();
                return;
            }
        }

        if (event.key === 'Escape') {
            clearCounterSelection();
        }
    });

    fetchCounterOrders(false);
    startCounterPolling();
    focusCounterInput();

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            return;
        }

        fetchCounterOrders(false);
        focusCounterInput();
    });
</script>
@endsection
