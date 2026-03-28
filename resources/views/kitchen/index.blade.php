@extends('layouts.app')

@section('title', 'شاشة المطبخ — Tarweaa')

@section('styles')
<style>
    .theme-toggle-btn {
        min-width: 110px;
        font-weight: 700;
    }
    .kitchen-screen {
        color: var(--text-primary);
        min-height: 100dvh;
    }
    .kitchen-screen.theme-light {
        --bg-primary: #f4f7fb;
        --bg-secondary: #ffffff;
        --bg-card: #ffffff;
        --bg-card-hover: #eef2ff;
        --bg-input: #ffffff;
        --border: #d7deea;
        --border-focus: rgba(99, 102, 241, 0.45);
        --text-primary: #111827;
        --text-secondary: #4b5563;
        --text-muted: #6b7280;
        --shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
        --danger-bg: #fef2f2;
    }
    .kitchen-screen.theme-light {
        background:
            radial-gradient(circle at top left, rgba(59, 130, 246, 0.12), transparent 32%),
            linear-gradient(180deg, #f8fbff 0%, #eef3f9 100%);
    }
    .kitchen-screen.theme-light .order-header {
        background: rgba(79, 70, 229, 0.04);
    }
    .kitchen-screen.theme-light .order-footer {
        background: rgba(15, 23, 42, 0.04);
    }
    .kitchen-screen.theme-light .order-card,
    .kitchen-screen.theme-light .kitchen-totals,
    .kitchen-screen.theme-light .kitchen-keypad-box {
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }
    .kitchen-screen.theme-light .item-note {
        color: #b91c1c;
        border-color: rgba(239, 68, 68, 0.12);
    }
    .kitchen-screen.theme-light .item-main,
    .kitchen-screen.theme-light .kitchen-totals__title,
    .kitchen-screen.theme-light .kitchen-total-row__name {
        color: #111827;
    }
    .kitchen-screen.theme-light .order-priority-badge {
        color: #b45309;
        background: rgba(245, 158, 11, 0.18);
    }
    .kitchen-screen.theme-light #order-count {
        background: #ffffff;
        color: #1f2937;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }
    .kitchen-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }
    .kitchen-main {
        min-width: 0;
        min-height: 0;
        overflow: hidden;
    }
    .kitchen-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem;
        height: 100%;
        overflow-y: auto;
        align-content: start;
    }
    .kitchen-totals {
        border-inline-start: 1px solid var(--border);
        background: var(--bg-secondary);
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .kitchen-totals__header {
        padding: 1.2rem 1rem 1rem;
        border-bottom: 1px solid var(--border);
    }
    .kitchen-totals__title {
        font-size: 1rem;
        font-weight: 800;
        margin: 0;
    }
    .kitchen-totals__subtitle {
        margin-top: 0.35rem;
        color: var(--text-secondary);
        font-size: 0.8rem;
        line-height: 1.5;
    }
    .kitchen-totals__list {
        flex: 1;
        overflow-y: auto;
        padding: 0.85rem 0.85rem 1rem;
    }
    .kitchen-totals__empty {
        color: var(--text-secondary);
        font-size: 0.85rem;
        text-align: center;
        padding: 1rem 0.5rem;
    }
    .kitchen-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 0.85rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--bg-card);
    }
    .kitchen-total-row + .kitchen-total-row {
        margin-top: 0.55rem;
    }
    .kitchen-total-row__name {
        font-size: 0.92rem;
        font-weight: 700;
        line-height: 1.4;
    }
    .kitchen-total-row__qty {
        min-width: 42px;
        text-align: center;
        padding: 0.3rem 0.6rem;
        border-radius: 999px;
        background: rgba(99, 102, 241, 0.14);
        border: 1px solid rgba(99, 102, 241, 0.25);
        color: var(--accent);
        font-size: 0.95rem;
        font-weight: 800;
    }
    .order-card {
        background: var(--bg-card);
        border: 2px solid var(--border);
        border-radius: var(--radius-lg);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s;
        box-shadow: var(--shadow);
    }
    .order-card.preparing {
        border-color: var(--warning);
    }
    .order-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255,255,255,0.03);
    }
    .order-number {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--accent);
    }
    .order-time {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    .order-items {
        padding: 1rem;
        flex: 1;
    }
    .item-row {
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px dashed var(--border);
    }
    .item-row:last-child {
        border-bottom: none;
    }
    .item-main {
        display: flex;
        justify-content: space-between;
        font-weight: 600;
        font-size: 1.1rem;
    }
    .item-qty {
        background: var(--accent);
        color: #fff;
        padding: 0 0.5rem;
        border-radius: 4px;
        min-width: 24px;
        text-align: center;
    }
    .item-note {
        margin-top: 0.25rem;
        background: var(--danger-bg);
        color: #fca5a5;
        padding: 0.4rem 0.6rem;
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
        font-weight: 700;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    .order-footer {
        padding: 1rem;
        background: rgba(0,0,0,0.2);
        display: flex;
        gap: 0.5rem;
    }
    .kitchen-topbar {
        height: 70px;
        background: var(--bg-secondary);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.5rem;
    }
    .kitchen-toolbar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .kitchen-keypad-box {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: 0.45rem 0.8rem;
        min-width: 320px;
    }
    .kitchen-keypad-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    .kitchen-keypad-input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: var(--text-primary);
        font-size: 1.15rem;
        font-weight: 800;
        text-align: center;
        letter-spacing: 0.06em;
    }
    .kitchen-keypad-hint {
        font-size: 0.75rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    .kitchen-keypad-status {
        margin-inline-start: 0.5rem;
        font-size: 0.8rem;
        color: var(--text-secondary);
        min-width: 90px;
    }
    .empty-state {
        grid-column: 1 / -1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 4rem;
        color: var(--text-secondary);
    }
    .badge-status {
        padding: 0.2rem 0.6rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    .order-card.selected-by-keypad {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18), var(--shadow);
        transform: translateY(-2px);
    }
    .order-shortcut {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.4rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-secondary);
    }
    .order-shortcut__value {
        color: var(--accent);
        font-size: 1rem;
    }
    .order-priority-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.5rem;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: rgba(245, 158, 11, 0.14);
        border: 1px solid rgba(245, 158, 11, 0.32);
        color: #fcd34d;
        font-size: 0.76rem;
        font-weight: 800;
    }
    @media (max-width: 960px) {
        .kitchen-layout {
            grid-template-columns: 1fr;
            overflow: auto;
        }
        .kitchen-main {
            overflow: visible;
        }
        .kitchen-grid {
            height: auto;
            overflow: visible;
        }
        .kitchen-totals {
            border-inline-start: none;
            border-top: 1px solid var(--border);
            min-height: 260px;
        }
        .kitchen-topbar {
            height: auto;
            align-items: stretch;
            flex-direction: column;
            gap: 0.75rem;
            padding: 1rem;
        }
        .kitchen-keypad-box {
            min-width: 100%;
        }
    }
</style>
@endsection

@section('content')
<div id="kitchen-screen" class="kitchen-screen flex flex-col h-screen">
    <div class="kitchen-topbar">
        <div class="kitchen-toolbar">
            <h1 class="text-xl font-bold">👨‍🍳 شاشة المطبخ</h1>
            <div id="connection-status" class="flex items-center gap-2 text-xs text-success">
                <span class="w-2 h-2 rounded-full bg-success"></span>
                متصل
            </div>
            <div class="kitchen-keypad-box">
                <span class="kitchen-keypad-label">رقم الطلب</span>
                <input
                    id="kitchen-order-input"
                    class="kitchen-keypad-input"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="مثال: 15"
                    aria-label="رقم الطلب"
                >
                <span class="kitchen-keypad-hint">اضغط Enter للتجهيز</span>
            </div>
            <div id="kitchen-keypad-status" class="kitchen-keypad-status">بانتظار الإدخال</div>
        </div>
        <div class="flex items-center gap-2">
            <div id="order-count" class="text-sm font-medium bg-bg-card px-3 py-1 rounded-full border border-border">0 طلب نشط</div>
            <button id="kitchen-theme-toggle" type="button" class="btn btn-sm btn-ghost theme-toggle-btn">☀️ فاتح</button>
            <button class="btn btn-sm btn-secondary" onclick="fetchOrders()">🔄 تحديث</button>
            <button id="kitchen-pos-link" class="btn btn-sm btn-ghost hidden" onclick="location.href='/pos'">نقطة البيع</button>
        </div>
    </div>

    <div class="kitchen-layout">
        <div class="kitchen-main">
            <div id="orders-container" class="kitchen-grid">
                {{-- Filled by JS --}}
                <div class="empty-state">
                    <div class="spinner mb-4"></div>
                    <p>جاري تحميل الطلبات...</p>
                </div>
            </div>
        </div>
        <aside class="kitchen-totals">
            <div class="kitchen-totals__header">
                <h2 class="kitchen-totals__title">Totals</h2>
                <p class="kitchen-totals__subtitle">إجمالي كميات الأصناف الموجودة حاليًا في جميع طلبات المطبخ.</p>
            </div>
            <div id="kitchen-totals-list" class="kitchen-totals__list">
                <div class="kitchen-totals__empty">جاري حساب الإجماليات...</div>
            </div>
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const OPS_THEME_KEY = 'tarweaa_ops_surface_theme';
    let activeOrders = [];
    let canMarkKitchenOrders = false;
    let selectedKitchenOrderId = null;

    const kitchenScreen = document.getElementById('kitchen-screen');
    const orderInput = document.getElementById('kitchen-order-input');
    const keypadStatus = document.getElementById('kitchen-keypad-status');
    const kitchenTotalsList = document.getElementById('kitchen-totals-list');
    const kitchenThemeToggle = document.getElementById('kitchen-theme-toggle');

    function applyKitchenTheme(theme) {
        const isLight = theme === 'light';

        kitchenScreen.classList.toggle('theme-light', isLight);
        document.body.style.background = isLight ? '#eef3f9' : '#0f1117';
        document.body.style.color = isLight ? '#111827' : '#e4e6eb';
        kitchenThemeToggle.textContent = isLight ? '🌙 داكن' : '☀️ فاتح';
        kitchenThemeToggle.setAttribute('aria-pressed', isLight ? 'true' : 'false');
    }

    function toggleKitchenTheme() {
        const nextTheme = kitchenScreen.classList.contains('theme-light') ? 'dark' : 'light';
        localStorage.setItem(OPS_THEME_KEY, nextTheme);
        applyKitchenTheme(nextTheme);
    }

    async function fetchOrders() {
        try {
            const res = await api('/orders?status=confirmed,preparing&per_page=50');
            if (res.success) {
                activeOrders = [...(res.data || [])].sort((left, right) => {
                    const leftTime = new Date(left.created_at || 0).getTime();
                    const rightTime = new Date(right.created_at || 0).getTime();

                    if (leftTime === rightTime) {
                        return (left.id || 0) - (right.id || 0);
                    }

                    return leftTime - rightTime;
                });
                syncSelectedOrderFromInput();
                renderOrders();
                document.getElementById('order-count').textContent = `${activeOrders.length} طلب نشط`;
            }
        } catch (err) {
            console.error('Fetch error:', err);
            showToast('فشل تحميل الطلبات', 'error');
        }
    }

    function renderOrders() {
        const container = document.getElementById('orders-container');
        if (activeOrders.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="text-4xl mb-4">🥣</span>
                    <p class="text-lg">لا توجد طلبات نشطة حالياً</p>
                    <p class="text-sm opacity-50">سيظهر أي طلب جديد هنا فور تأكيده</p>
                </div>
            `;
            renderKitchenTotals([]);
            return;
        }

            container.innerHTML = activeOrders.map((order, index) => `
            <div class="order-card ${order.status === 'preparing' ? 'preparing' : ''} ${selectedKitchenOrderId === order.id ? 'selected-by-keypad' : ''}" id="order-${order.id}">
                <div class="order-header">
                    <div>
                        <div class="order-number">#${order.order_number.split('-').pop()}</div>
                        <div class="order-time">${getTimeAgo(order.created_at)}</div>
                        ${index === 0 ? `<div class="order-priority-badge">⏳ الأقدم انتظارًا</div>` : ''}
                        <div class="order-shortcut">
                            <span>رقم لوحة الأرقام</span>
                            <span class="order-shortcut__value">${getKitchenShortcutNumber(order)}</span>
                        </div>
                    </div>
                    <div class="text-left">
                        <span class="badge-status ${getStatusColor(order.status)}">${order.status_label || order.status}</span>
                        <div class="text-xs mt-1 text-muted font-bold">${order.source === 'pos' ? (order.type_label || order.type) : order.source_label}</div>
                    </div>
                </div>
                <div class="order-items">
                    ${order.items.map(item => `
                        <div class="item-row">
                            <div class="item-main">
                                <span class="item-name">${item.item_name}${item.variant_name ? ` (${item.variant_name})` : ''}</span>
                                <span class="item-qty">${item.quantity}</span>
                            </div>
                            ${item.notes ? `<div class="item-note">📝 ${item.notes}</div>` : ''}
                            ${item.modifiers && item.modifiers.length ? `
                                <div class="text-xs text-muted mt-1">
                                    + ${item.modifiers.map(m => m.name).join(', ')}
                                </div>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
                <div class="order-footer">
                    ${order.status === 'confirmed' ? `
                        <button class="btn btn-block btn-secondary" onclick="markReady(${order.id}, 'preparing')">👨‍🍳 تحضير</button>
                    ` : ''}
                    <button class="btn btn-block btn-success" onclick="markReady(${order.id}, 'ready')">✅ جاهز</button>
                </div>
            </div>
        `).join('');

        renderKitchenTotals(activeOrders);

        if (selectedKitchenOrderId) {
            document.getElementById(`order-${selectedKitchenOrderId}`)?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        }
    }

    function getKitchenItemTotals(orders) {
        const totals = new Map();

        orders.forEach((order) => {
            (order.items || []).forEach((item) => {
                const label = `${item.item_name || ''}${item.variant_name ? ` (${item.variant_name})` : ''}`.trim();
                const quantity = Number(item.quantity || 0);

                if (!label || quantity <= 0) {
                    return;
                }

                totals.set(label, (totals.get(label) || 0) + quantity);
            });
        });

        return Array.from(totals.entries())
            .map(([name, quantity]) => ({ name, quantity }))
            .sort((left, right) => {
                if (right.quantity === left.quantity) {
                    return left.name.localeCompare(right.name, 'ar');
                }

                return right.quantity - left.quantity;
            });
    }

    function renderKitchenTotals(orders) {
        const totals = getKitchenItemTotals(orders);

        if (totals.length === 0) {
            kitchenTotalsList.innerHTML = `
                <div class="kitchen-totals__empty">لا توجد أصناف نشطة في المطبخ حاليًا.</div>
            `;
            return;
        }

        kitchenTotalsList.innerHTML = totals.map((item) => `
            <div class="kitchen-total-row">
                <div class="kitchen-total-row__name">${item.name}</div>
                <div class="kitchen-total-row__qty">${item.quantity}</div>
            </div>
        `).join('');
    }

    function getKitchenOrderById(orderId) {
        return activeOrders.find(order => order.id === orderId) || null;
    }

    async function transitionKitchenOrder(orderId, status) {
        return api(`/orders/${orderId}/status`, {
            method: 'PATCH',
            body: { status: status }
        });
    }

    async function markReady(orderId, status, button = null) {
        if (!canMarkKitchenOrders) {
            showToast('ليس لديك صلاحية لتحديث حالة الطلبات من شاشة المطبخ', 'error');
            return;
        }

        const order = getKitchenOrderById(orderId);
        const btn = button || null;
        const originalText = btn ? btn.textContent : null;

        if (btn) {
            btn.disabled = true;
            btn.textContent = '...';
        }

        try {
            let res;

            if (status === 'ready' && order?.status === 'confirmed') {
                await transitionKitchenOrder(orderId, 'preparing');
                res = await transitionKitchenOrder(orderId, 'ready');
            } else {
                res = await transitionKitchenOrder(orderId, status);
            }

            if (res.success) {
                clearKitchenSelection();
                showToast(status === 'ready' ? 'الطلب جاهز!' : 'بدأ التحضير');
                fetchOrders();
            }
        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            showToast(err.message || 'خطأ في التحديث', 'error');
        }
    }

    function getKitchenShortcutNumber(order) {
        const suffix = String(order.order_number || '').split('-').pop() || '';
        const numeric = parseInt(suffix, 10);

        return Number.isNaN(numeric) ? suffix : String(numeric);
    }

    function findKitchenOrderByTypedNumber(value) {
        const normalized = String(value || '').trim();

        if (!normalized) {
            return null;
        }

        return activeOrders.find(order => getKitchenShortcutNumber(order) === normalized) || null;
    }

    function setKitchenStatusMessage(message, tone = 'muted') {
        keypadStatus.textContent = message;
        keypadStatus.style.color = tone === 'success'
            ? 'var(--success)'
            : tone === 'danger'
                ? 'var(--danger)'
                : tone === 'warning'
                    ? 'var(--warning)'
                    : 'var(--text-secondary)';
    }

    function clearKitchenSelection() {
        selectedKitchenOrderId = null;
        orderInput.value = '';
        setKitchenStatusMessage('بانتظار الإدخال');
        renderOrders();
        focusKitchenInput();
    }

    function syncSelectedOrderFromInput() {
        const match = findKitchenOrderByTypedNumber(orderInput.value);

        if (!orderInput.value.trim()) {
            selectedKitchenOrderId = null;
            setKitchenStatusMessage('بانتظار الإدخال');
            return;
        }

        if (match) {
            selectedKitchenOrderId = match.id;
            setKitchenStatusMessage(`تم تحديد الطلب #${getKitchenShortcutNumber(match)}`, 'success');
            return;
        }

        selectedKitchenOrderId = null;
        setKitchenStatusMessage('رقم الطلب غير موجود', 'danger');
    }

    async function submitKitchenShortcut() {
        const match = findKitchenOrderByTypedNumber(orderInput.value);

        if (!match) {
            setKitchenStatusMessage('لا يوجد طلب بهذا الرقم', 'danger');
            showToast('رقم الطلب غير موجود في الشاشة الحالية', 'error');
            return;
        }

        selectedKitchenOrderId = match.id;
        renderOrders();
        setKitchenStatusMessage(`جارٍ تجهيز الطلب #${getKitchenShortcutNumber(match)}...`, 'warning');
        await markReady(match.id, 'ready');
    }

    function focusKitchenInput() {
        orderInput?.focus();
        orderInput?.select();
    }

    function getStatusColor(status) {
        switch(status) {
            case 'confirmed': return 'badge-secondary';
            case 'preparing': return 'badge-warning';
            case 'ready': return 'badge-success';
            default: return 'badge-ghost';
        }
    }

    function getTimeAgo(dateStr) {
        const now = new Date();
        const past = new Date(dateStr);
        const diffMs = now - past;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return 'الآن';
        if (diffMins < 60) return `${diffMins} د`;
        return `${Math.floor(diffMins/60)} س`;
    }

    // Auth check
    if (!requireAuth('/kitchen')) {
        throw new Error('Unauthenticated');
    }

    if (!canAccessKitchenSurface()) {
        showToast('ليس لديك صلاحية لعرض شاشة المطبخ', 'error');
        setTimeout(() => redirectToAuthorizedHome(), 800);
        throw new Error('Forbidden');
    }

    canMarkKitchenOrders = hasPermission('mark_order_ready');
    if (canAccessPosSurface()) {
        document.getElementById('kitchen-pos-link')?.classList.remove('hidden');
    }
    applyKitchenTheme(localStorage.getItem(OPS_THEME_KEY) || 'dark');
    kitchenThemeToggle?.addEventListener('click', toggleKitchenTheme);

    orderInput?.addEventListener('input', () => {
        orderInput.value = orderInput.value.replace(/[^\d]/g, '');
        syncSelectedOrderFromInput();
        renderOrders();
    });

    orderInput?.addEventListener('keydown', async (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            await submitKitchenShortcut();
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            clearKitchenSelection();
        }
    });

    document.addEventListener('keydown', async (event) => {
        const target = event.target;
        const isEditable = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target?.isContentEditable;

        if (!isEditable) {
            if (/^\d$/.test(event.key)) {
                orderInput.value = `${orderInput.value}${event.key}`;
                syncSelectedOrderFromInput();
                renderOrders();
                focusKitchenInput();
                return;
            }

            if (event.key === 'Backspace') {
                orderInput.value = orderInput.value.slice(0, -1);
                syncSelectedOrderFromInput();
                renderOrders();
                focusKitchenInput();
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                await submitKitchenShortcut();
                return;
            }
        }
    });

    document.addEventListener('click', () => {
        focusKitchenInput();
    });

    // Initial load
    fetchOrders();
    focusKitchenInput();

    // Auto refresh every 15 seconds
    setInterval(fetchOrders, 15000);
</script>
@endsection
