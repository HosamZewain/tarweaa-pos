@extends('layouts.app')

@section('title', 'شاشة المطبخ — Tarweaa')

@section('styles')
<style>
    .kitchen-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem;
        height: calc(100vh - 80px);
        overflow-y: auto;
        align-content: start;
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
    @media (max-width: 960px) {
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
<div class="flex flex-col h-screen">
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
            <button class="btn btn-sm btn-secondary" onclick="fetchOrders()">🔄 تحديث</button>
            <button id="kitchen-pos-link" class="btn btn-sm btn-ghost hidden" onclick="location.href='/pos'">نقطة البيع</button>
        </div>
    </div>

    <div id="orders-container" class="kitchen-grid">
        {{-- Filled by JS --}}
        <div class="empty-state">
            <div class="spinner mb-4"></div>
            <p>جاري تحميل الطلبات...</p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let activeOrders = [];
    let canMarkKitchenOrders = false;
    let selectedKitchenOrderId = null;

    const orderInput = document.getElementById('kitchen-order-input');
    const keypadStatus = document.getElementById('kitchen-keypad-status');

    async function fetchOrders() {
        try {
            const res = await api('/orders?status=confirmed,preparing&per_page=50');
            if (res.success) {
                activeOrders = res.data;
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
            return;
        }

        container.innerHTML = activeOrders.map(order => `
            <div class="order-card ${order.status === 'preparing' ? 'preparing' : ''} ${selectedKitchenOrderId === order.id ? 'selected-by-keypad' : ''}" id="order-${order.id}">
                <div class="order-header">
                    <div>
                        <div class="order-number">#${order.order_number.split('-').pop()}</div>
                        <div class="order-time">${getTimeAgo(order.created_at)}</div>
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

        if (selectedKitchenOrderId) {
            document.getElementById(`order-${selectedKitchenOrderId}`)?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        }
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
