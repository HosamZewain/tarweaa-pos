@extends('layouts.app')

@section('title', 'شاشة المطبخ — طرعة')

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
</style>
@endsection

@section('content')
<div class="flex flex-col h-screen">
    <div class="kitchen-topbar">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-bold">👨‍🍳 شاشة المطبخ</h1>
            <div id="connection-status" class="flex items-center gap-2 text-xs text-success">
                <span class="w-2 h-2 rounded-full bg-success"></span>
                متصل
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div id="order-count" class="text-sm font-medium bg-bg-card px-3 py-1 rounded-full border border-border">0 طلب نشط</div>
            <button class="btn btn-sm btn-secondary" onclick="fetchOrders()">🔄 تحديث</button>
            <button class="btn btn-sm btn-ghost" onclick="location.href='/pos'">نقطة البيع</button>
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

    async function fetchOrders() {
        try {
            const res = await api('/orders?status=confirmed,preparing&per_page=50');
            if (res.success) {
                activeOrders = res.data;
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
            <div class="order-card ${order.status === 'preparing' ? 'preparing' : ''}" id="order-${order.id}">
                <div class="order-header">
                    <div>
                        <div class="order-number">#${order.order_number.split('-').pop()}</div>
                        <div class="order-time">${getTimeAgo(order.created_at)}</div>
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
    }

    async function markReady(orderId, status) {
        const btn = event.currentTarget;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '...';

        try {
            const res = await api(`/orders/${orderId}/status`, {
                method: 'PATCH',
                body: { status: status }
            });

            if (res.success) {
                showToast(status === 'ready' ? 'الطلب جاهز!' : 'بدأ التحضير');
                fetchOrders();
            }
        } catch (err) {
            btn.disabled = false;
            btn.textContent = originalText;
            showToast(err.message || 'خطأ في التحديث', 'error');
        }
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
    if (!requireAuth()) {
        window.location.href = '/pos/login';
    }

    // Initial load
    fetchOrders();

    // Auto refresh every 15 seconds
    setInterval(fetchOrders, 15000);
</script>
@endsection
