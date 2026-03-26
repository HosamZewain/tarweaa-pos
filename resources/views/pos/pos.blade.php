@extends('layouts.app')
@section('title', 'نقطة البيع — طرعة POS')

@section('styles')
<style>
    /* ═══ POS Layout ═══ */
    .pos-container { display: flex; height: 100dvh; overflow: hidden; }
    .pos-menu { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .pos-cart { width: 380px; border-right: 1px solid var(--border); display: flex; flex-direction: column; background: var(--bg-secondary); flex-shrink: 0; }

    /* ═══ Topbar ═══ */
    .pos-topbar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.5rem 1rem; background: var(--bg-secondary);
        border-bottom: 1px solid var(--border); min-height: 52px; gap: 0.75rem;
    }
    .topbar-info { display: flex; align-items: center; gap: 1rem; font-size: 0.8rem; }
    .topbar-info .item { display: flex; align-items: center; gap: 0.35rem; color: var(--text-secondary); }
    .topbar-info .item strong { color: var(--text-primary); }
    .topbar-actions { display: flex; gap: 0.5rem; }

    /* ═══ Order Type Bar ═══ */
    .type-bar {
        display: flex; gap: 0.5rem; padding: 0.75rem 1rem;
        background: var(--bg-secondary);
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }
    .type-tab {
        flex: 1; padding: 0.75rem; border-radius: var(--radius);
        border: 2px solid var(--border); background: var(--bg-card);
        color: var(--text-secondary); font-weight: 700; text-align: center;
        cursor: pointer; transition: all 0.15s; font-size: 0.95rem;
    }
    .type-tab.active { border-color: var(--accent); background: rgba(99, 102, 241, 0.1); color: var(--accent); }

    /* ═══ Categories ═══ */
    .cat-bar {
        display: flex; gap: 0.5rem; padding: 0.75rem 1rem;
        overflow-x: auto; flex-shrink: 0; background: var(--bg-primary);
        border-bottom: 1px solid var(--border);
    }
    .cat-bar::-webkit-scrollbar { display: none; }
    .cat-btn {
        padding: 0.6rem 1.25rem; border-radius: 999px;
        border: 1px solid var(--border); background: var(--bg-card);
        color: var(--text-secondary); font-weight: 600; font-size: 0.9rem;
        cursor: pointer; white-space: nowrap; transition: all 0.15s;
    }
    .cat-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* ═══ Items Grid ═══ */
    .items-grid {
        flex: 1; overflow-y: auto; padding: 1rem;
        display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 0.75rem; align-content: start;
    }
    .item-card {
        background: var(--bg-card); border: 1px solid var(--border);
        border-radius: var(--radius); padding: 1rem 0.75rem;
        text-align: center; cursor: pointer; transition: all 0.15s;
        display: flex; flex-direction: column; gap: 0.35rem; min-height: 100px;
        justify-content: center;
    }
    .item-card:active { transform: scale(0.96); background: var(--bg-card-hover); border-color: var(--accent); }
    .item-name { font-size: 0.9rem; font-weight: 600; line-height: 1.3; }
    .item-price { font-size: 0.85rem; color: var(--accent); font-weight: 700; direction: ltr; }

    /* ═══ Cart ═══ */
    .cart-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border);
        font-weight: 700; font-size: 1rem;
        display: flex; align-items: center; justify-content: space-between;
    }
    .cart-items { flex: 1; overflow-y: auto; padding: 0.5rem; }
    .cart-empty { text-align: center; padding: 3rem 1rem; color: var(--text-muted); font-size: 0.95rem; }

    .cart-item {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.6rem 0.5rem; border-bottom: 1px solid var(--border);
    }
    .cart-item-info { flex: 1; }
    .cart-item-name { font-size: 0.85rem; font-weight: 600; }
    .cart-item-detail { font-size: 0.75rem; color: var(--text-secondary); }
    .cart-item-price { font-size: 0.85rem; font-weight: 700; color: var(--accent); direction: ltr; white-space: nowrap; }
    .cart-qty {
        display: flex; align-items: center; gap: 0.25rem;
    }
    .cart-qty button {
        width: 30px; height: 30px; border: 1px solid var(--border);
        border-radius: var(--radius-sm); background: var(--bg-card);
        color: var(--text-primary); font-size: 1rem; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .cart-qty button:active { background: var(--accent); color: #fff; }
    .cart-qty span { width: 24px; text-align: center; font-weight: 600; font-size: 0.9rem; }
    .cart-remove { color: var(--danger); cursor: pointer; font-size: 0.8rem; padding: 0.25rem; }

    /* ═══ Cart Footer ═══ */
    .cart-footer { border-top: 1px solid var(--border); padding: 0.75rem 1rem; flex-shrink: 0; }
    .cart-totals { display: flex; flex-direction: column; gap: 0.375rem; margin-bottom: 0.75rem; }
    .cart-total-row { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-secondary); }
    .cart-total-row.grand { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); padding-top: 0.5rem; border-top: 1px solid var(--border); }
    .cart-total-row .val { direction: ltr; }

    /* ═══ Payment Modal ═══ */
    .pay-methods { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-bottom: 1rem; }
    .pay-method {
        padding: 0.75rem; border-radius: var(--radius); border: 2px solid var(--border);
        background: var(--bg-card); text-align: center; cursor: pointer;
        font-weight: 600; font-size: 0.9rem; transition: all 0.15s;
    }
    .pay-method.active { border-color: var(--accent); background: rgba(99, 102, 241, 0.1); }
    .pay-amount-section { margin-bottom: 1rem; }
    .change-display { text-align: center; padding: 0.75rem; border-radius: var(--radius); margin-bottom: 1rem; }
    .change-display.positive { background: var(--success-bg); color: var(--success); }
    .change-display.zero { background: var(--bg-card); color: var(--text-secondary); }
</style>
@endsection

@section('content')
{{-- Loading --}}
<div id="pos-loading" class="loading-screen"><div class="spinner"></div></div>

<div id="pos-app" class="pos-container hidden">
    {{-- ═══ LEFT: Menu Area ═══ --}}
    <div class="pos-menu">
        {{-- Topbar --}}
        <div class="pos-topbar">
            <div class="topbar-info">
                <div class="item">👤 <strong id="tp-cashier">—</strong></div>
                <div class="item">🗂 وردية <strong id="tp-shift">—</strong></div>
                <div class="item" onclick="openCustomerModal()" style="cursor:pointer; color:var(--accent)">
                    👤 <strong id="tp-customer">نقدي (تغيير)</strong>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="btn btn-sm btn-secondary" onclick="openMovementModal()">
                    <span class="text-sm">🗄️ حركة الدرج</span>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="openSessionModal()">
                    <span class="text-sm">📊 الجلسة الحالية</span>
                </button>
                <button class="btn btn-sm btn-ghost text-danger" onclick="logout()">
                    خروج
                </button>
            </div>
        </div>

        {{-- Order Types --}}
        <div class="type-bar" id="type-bar"></div>

        {{-- Categories --}}
        <div class="cat-bar" id="cat-bar"></div>

        {{-- Items grid --}}
        <div class="items-grid" id="items-grid">
            <div class="cart-empty">جاري تحميل القائمة...</div>
        </div>
    </div>

    {{-- ═══ RIGHT: Cart Panel ═══ --}}
    <div class="pos-cart">
        <div class="cart-header">
            <div>
                <span id="cart-order-type" style="display:block; font-size:0.75rem; color:var(--accent); font-weight:800; cursor:pointer" onclick="openTypeModal()">—</span>
                <span>🛒 الطلب الحالي</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-accent cursor-pointer text-xs font-bold" onclick="openDiscountModal()">🏷️ خصم</span>
                <span class="text-sm text-muted" id="cart-count">0 منتج</span>
            </div>
        </div>

        <div class="cart-items" id="cart-items">
            <div class="cart-empty">لا توجد منتجات بعد</div>
        </div>

        <div class="cart-footer">
            <div class="cart-totals">
                <div class="cart-total-row"><span>المجموع الفرعي</span><span class="val" id="t-subtotal">0.00 ج.م</span></div>
                <div class="cart-total-row"><span>الضريبة (15%)</span><span class="val" id="t-tax">0.00 ج.م</span></div>
                <div class="cart-total-row grand"><span>الإجمالي</span><span class="val" id="t-total">0.00 ج.م</span></div>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-secondary flex-1" onclick="clearCart()" id="btn-clear" disabled>مسح</button>
                <button class="btn btn-primary flex-1" onclick="showPayModal()" id="btn-pay" disabled style="flex:2">💳 دفع</button>
            </div>
        </div>
    </div>
</div>

{{-- ═══ Payment Modal ═══ --}}
<div id="pay-modal" class="modal-overlay hidden" onclick="event.target===this && closePayModal()">
    <div class="modal-content">
        <div class="modal-title">💳 الدفع</div>
        <div class="cart-total-row grand" style="margin-bottom:1rem; padding:0; border:0;">
            <span>المطلوب</span><span class="val" id="pm-total">0.00 ج.م</span>
        </div>

        <div class="pay-methods">
            <div class="pay-method active" data-method="cash" onclick="selectPayMethod('cash')">💵 نقدي</div>
            <div class="pay-method" data-method="card" onclick="selectPayMethod('card')">💳 بطاقة</div>
        </div>

        <div class="pay-amount-section" id="pay-cash-section">
            <div class="form-group mb-4">
                <label class="form-label">المبلغ المدفوع</label>
                <input type="text" id="pay-amount" class="form-input form-input-lg" 
                       readonly onclick="openNumPad('pay-amount', 'المبلغ المدفوع')" placeholder="0.00">
            </div>
            <div class="change-display zero" id="change-display">الباقي: 0.00 ج.م</div>
        </div>

        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1" onclick="closePayModal()">إلغاء</button>
            <button class="btn btn-success flex-1" id="btn-confirm-pay" onclick="confirmPayment()" style="flex:2">تأكيد الدفع</button>
        </div>
    </div>
</div>


{{-- ═══ Customer Modal ═══ --}}
<div id="customer-modal" class="modal-overlay hidden" onclick="event.target===this && closeCustomerModal()">
    {{-- ... content ... --}}
    <div class="modal-content" style="max-width:600px">
        <div class="modal-title">👤 العميل</div>
        
        <div class="tabs flex gap-2 mb-4">
            <button class="btn btn-sm btn-ghost active flex-1" id="tab-search" onclick="showCustomerTab('search')">بحث عن عميل</button>
            <button class="btn btn-sm btn-ghost flex-1" id="tab-new" onclick="showCustomerTab('new')">عميل جديد</button>
        </div>

        {{-- Search Tab --}}
        <div id="customer-search-section">
            <div class="form-group mb-4">
                <input type="text" id="cust-search-input" class="form-input" placeholder="ابحث بالاسم أو رقم الهاتف..." oninput="searchCustomers()">
            </div>
            <div id="cust-results" style="max-height:300px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius)">
                {{-- Results --}}
                <div class="text-center p-4 text-muted">ابحث للبدء...</div>
            </div>
        </div>

        {{-- New Customer Tab --}}
        <div id="customer-new-section" class="hidden">
            <div class="form-group mb-2">
                <label class="form-label">الاسم</label>
                <input type="text" id="nc-name" class="form-input">
            </div>
            <div class="form-group mb-2">
                <label class="form-label">رقم الهاتف</label>
                <input type="text" id="nc-phone" class="form-input">
            </div>
            <div class="form-group mb-4">
                <label class="form-label">العنوان (اختياري)</label>
                <textarea id="nc-address" class="form-input" rows="2"></textarea>
            </div>
            <button class="btn btn-primary btn-block" onclick="createNewCustomer()">إضافة واختيار</button>
        </div>

        <div class="flex gap-2 mt-4">
            <button class="btn btn-secondary flex-1" onclick="closeCustomerModal()">إغلاق</button>
            <button class="btn btn-ghost flex-1" onclick="selectCustomer(null)">بدون عميل (نقدي)</button>
        </div>
    </div>
</div>

{{-- ═══ Item Note Modal ═══ --}}
<div id="note-modal" class="modal-overlay hidden" onclick="event.target===this && closeNoteModal()">
    <div class="modal-content">
        <div class="modal-title">📝 ملاحظات المنتج</div>
        <div class="form-group">
            <label class="form-label" id="note-item-name">—</label>
            <textarea id="item-note-input" class="form-input" rows="3" placeholder="مثلاً: بدون بصل، زيادة شطة..."></textarea>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1" onclick="closeNoteModal()">إلغاء</button>
            <button class="btn btn-primary flex-1" onclick="saveItemNote()">حفظ</button>
        </div>
    </div>
</div>

{{-- ═══ Discount Modal ═══ --}}
<div id="discount-modal" class="modal-overlay hidden" onclick="event.target===this && closeDiscountModal()">
    <div class="modal-content">
        <div class="modal-title">📉 تطبيق خصم</div>
        <div class="form-group">
            <label class="form-label">نوع الخصم</label>
            <select id="disc-type" class="form-input">
                <option value="percentage">نسبة (%)</option>
                <option value="fixed">مبلغ ثابت (ج.م)</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">القيمة</label>
            <input type="text" id="disc-value" class="form-input form-input-lg" 
                   readonly onclick="openNumPad('disc-value', 'القيمة')" placeholder="0.00">
        </div>
        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1" onclick="closeDiscountModal()">إلغاء</button>
            <button class="btn btn-primary flex-1" onclick="applyDiscount()">تطبيق</button>
        </div>
    </div>
</div>

{{-- ═══ Session Info Modal ═══ --}}
<div id="session-modal" class="modal-overlay hidden" onclick="event.target===this && closeSessionModal()">
    <div class="modal-content" style="max-width: 800px; width: 95%;">
        <div class="modal-title flex justify-between items-center">
            <span>📊 ملخص الجلسة الحالية</span>
            <button class="btn btn-sm btn-ghost" onclick="closeSessionModal()">✕</button>
        </div>
        
        <div class="tabs flex gap-2 mb-4 border-b border-border pb-2">
            <button class="btn btn-sm btn-ghost active" id="tab-session-orders" onclick="showSessionTab('orders')">📦 طلبات الجلسة</button>
            <button class="btn btn-sm btn-ghost" id="tab-session-stats" onclick="showSessionTab('stats')">💰 إحصائيات مالية</button>
        </div>

        {{-- Session Orders Tab --}}
        <div id="session-orders-content">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead>
                        <tr class="bg-bg-secondary">
                            <th class="p-2 border-b">رقم الطلب</th>
                            <th class="p-2 border-b">الوقت</th>
                            <th class="p-2 border-b">النوع</th>
                            <th class="p-2 border-b">الحالة</th>
                            <th class="p-2 border-b">الدفع</th>
                            <th class="p-2 border-b">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody id="session-orders-list">
                        {{-- Filled by JS --}}
                    </tbody>
                </table>
            </div>
            <div id="session-orders-loading" class="text-center p-4">
                <div class="spinner mx-auto mb-2"></div>
                <p class="text-xs text-muted">جاري تحميل الطلبات...</p>
            </div>
        </div>

        {{-- Session Stats Tab --}}
        <div id="session-stats-content" class="hidden">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="session-stats-grid">
                {{-- Filled by JS --}}
            </div>
            <div id="session-stats-loading" class="text-center p-4">
                <div class="spinner mx-auto mb-2"></div>
                <p class="text-xs text-muted">جاري تحميل الإحصائيات...</p>
            </div>
        </div>
    </div>
</div>
<div id="movement-modal" class="modal-overlay hidden" onclick="event.target===this && closeMovementModal()">
    <div class="modal-content">
        <div class="modal-title">🗄️ حركة الدرج النقدية</div>
        <div class="tabs flex gap-2 mb-4">
            <button class="btn btn-sm btn-ghost active flex-1" id="tab-cashin" onclick="showMoveTab('in')">إيداع (+)</button>
            <button class="btn btn-sm btn-ghost flex-1" id="tab-cashout" onclick="showMoveTab('out')">سحب (-)</button>
        </div>
        <div class="form-group mb-4">
            <label class="form-label">المبلغ</label>
            <input type="text" id="move-amount" class="form-input form-input-lg" 
                   readonly onclick="openNumPad('move-amount', 'مبلغ الحركة')" placeholder="0.00">
        </div>
        <div class="form-group mb-4">
            <label class="form-label">السبب / البيان</label>
            <input type="text" id="move-reason" class="form-input" placeholder="مثلاً: شراء خضار، إضافة فكة...">
        </div>
        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1" onclick="closeMovementModal()">إلغاء</button>
            <button class="btn btn-primary flex-1" id="move-btn" onclick="processMovement()">تأكيد</button>
        </div>
        <div class="mt-4 pt-4 border-t border-border text-center">
            <a href="/pos/drawer/close" class="text-danger text-sm font-bold">⚠️ إغلاق الوردية والدرج</a>
        </div>
    </div>
</div>
</div>
@endsection

@section('scripts')
<script>
    if (!requireAuth()) throw 'no-auth';

    // ═══ State ═══
    let menuData = [];
    let orderTypes = [];
    let selectedOrderType = null; // {id, name, type, source}
    let selectedCustomer = null;  // {id, name, ...}
    let activeCatId = null;
    let cart = [];
    let currentOrder = null;
    let selectedPayMethod = 'cash';
    let drawerSession = null;
    let activeShift = null;

    const TAX_RATE = 0;

    // ═══ Init ═══
    (async function init() {
        try {
            // Check active drawer
            const res = await api('/drawers/active');
            if (!res?.data) {
                window.location.href = '/pos/drawer';
                return;
            }
            window.currentDrawerSessionId = res.data.id;
            drawerSession = res.data;

            // Get status
            const sRes = await api('/pos/status');
            if (sRes?.data) {
                document.getElementById('tp-cashier').textContent = sRes.data.cashier?.name || '—';
                document.getElementById('tp-shift').textContent = sRes.data.shift?.shift_number || '#' + (sRes.data.shift?.id || '—');
                activeShift = sRes.data.shift;
            }

            // Load Order Types
            const tRes = await api('/pos/order-types');
            if (tRes?.data) {
                orderTypes = tRes.data;
                renderOrderTypes();
                // Auto-select first type
                if (orderTypes.length > 0) selectType(orderTypes[0].id);
            }

            // Load menu
            const mRes = await api('/pos/menu');
            if (mRes?.data) {
                menuData = mRes.data;
                renderCategories();
            }
        } catch (err) {
            showToast(err.message || 'خطأ في تحميل البيانات', 'error');
        }

        document.getElementById('pos-loading').classList.add('hidden');
        document.getElementById('pos-app').classList.remove('hidden');
    })();

    // ═══ Order Types ═══
    function renderOrderTypes() {
        const bar = document.getElementById('type-bar');
        bar.innerHTML = orderTypes.map(t => `
            <div class="type-tab ${selectedOrderType?.id === t.id ? 'active' : ''}" 
                 id="type-tab-${t.id}"
                 onclick="selectType(${t.id})">
                ${t.name}
            </div>
        `).join('');
    }

    function selectType(id) {
        selectedOrderType = orderTypes.find(t => t.id === id);
        
        // UI Update
        document.querySelectorAll('.type-tab').forEach(el => el.classList.remove('active'));
        document.getElementById(`type-tab-${id}`)?.classList.add('active');
        
        const cartLabel = document.getElementById('cart-order-type');
        if (cartLabel) cartLabel.textContent = selectedOrderType.name;
        
        showToast(`نوع الطلب: ${selectedOrderType.name}`);
    }

    // ═══ Customers ═══
    function openCustomerModal() {
        document.getElementById('customer-modal').classList.remove('hidden');
        document.getElementById('cust-search-input').focus();
    }

    function closeCustomerModal() {
        document.getElementById('customer-modal').classList.add('hidden');
    }

    function showCustomerTab(tab) {
        document.getElementById('customer-search-section').classList.toggle('hidden', tab !== 'search');
        document.getElementById('customer-new-section').classList.toggle('hidden', tab !== 'new');
        document.getElementById('tab-search').classList.toggle('active', tab === 'search');
        document.getElementById('tab-new').classList.toggle('active', tab === 'new');
    }

    let searchResults = [];

    async function searchCustomers() {
        const search = document.getElementById('cust-search-input').value;
        if (search.length < 2) return;

        try {
            const res = await api(`/pos/customers?search=${encodeURIComponent(search)}`);
            const results = document.getElementById('cust-results');
            searchResults = res.data;
            if (!res.data.length) {
                results.innerHTML = '<div class="text-center p-4 text-muted">لا يوجد نتائج</div>';
                return;
            }
            results.innerHTML = res.data.map((c, i) => `
                <div class="p-3 border-bottom cursor-pointer hover-bg" onclick="selectCustomerByIndex(${i})">
                    <div class="font-bold">${c.name}</div>
                    <div class="text-sm text-secondary">${c.phone}</div>
                </div>
            `).join('');
        } catch (err) {}
    }

    function selectCustomerByIndex(idx) {
        selectCustomer(searchResults[idx]);
    }

    function selectCustomer(customer) {
        selectedCustomer = customer;
        const el = document.getElementById('tp-customer');
        if (customer) {
            el.textContent = `${customer.name} (${customer.phone})`;
            showToast(`تم اختيار العميل: ${customer.name}`);
        } else {
            el.textContent = 'نقدي (تغيير)';
        }
        closeCustomerModal();
    }

    async function createNewCustomer() {
        const name = document.getElementById('nc-name').value;
        const phone = document.getElementById('nc-phone').value;
        const address = document.getElementById('nc-address').value;

        if (!name || !phone) { showToast('الاسم ورقم الهاتف مطلوبان', 'error'); return; }

        try {
            const res = await api('/pos/customers', {
                method: 'POST',
                body: { name, phone, address }
            });
            selectCustomer(res.data);
            // Clear inputs
            document.getElementById('nc-name').value = '';
            document.getElementById('nc-phone').value = '';
            document.getElementById('nc-address').value = '';
        } catch (err) {
            showToast(err.message || 'خطأ في إضافة العميل', 'error');
        }
    }

    // ═══ Categories ═══
    function renderCategories() {
        const bar = document.getElementById('cat-bar');
        bar.innerHTML = '';

        // "All" button
        const allBtn = document.createElement('button');
        allBtn.className = 'cat-btn active';
        allBtn.textContent = 'الكل';
        allBtn.onclick = () => selectCategory(null, allBtn);
        bar.appendChild(allBtn);

        menuData.forEach(cat => {
            const btn = document.createElement('button');
            btn.className = 'cat-btn';
            btn.textContent = cat.name;
            btn.onclick = () => selectCategory(cat.id, btn);
            bar.appendChild(btn);
        });

        renderItems(null);
    }

    function selectCategory(catId, btn) {
        activeCatId = catId;
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderItems(catId);
    }

    // ═══ Items ═══
    function renderItems(catId) {
        const grid = document.getElementById('items-grid');
        grid.innerHTML = '';

        let items = [];
        if (catId === null) {
            menuData.forEach(cat => {
                (cat.menu_items || []).forEach(item => items.push(item));
            });
        } else {
            const cat = menuData.find(c => c.id === catId);
            items = cat?.menu_items || [];
        }

        if (!items.length) {
            grid.innerHTML = '<div class="cart-empty">لا توجد منتجات</div>';
            return;
        }

        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'item-card';
            card.onclick = () => addToCart(item);

            const price = item.variants?.length
                ? item.variants[0].price
                : (item.price || item.base_price || 0);

            card.innerHTML = `
                <div class="item-name">${item.name}</div>
                <div class="item-price">${parseFloat(price).toFixed(2)} ج.م</div>
            `;
            grid.appendChild(card);
        });
    }

    // ═══ Cart Logic ═══
    function addToCart(item) {
        const variant = item.variants?.length ? item.variants[0] : null;
        const key = `${item.id}-${variant?.id || 0}`;

        const existing = cart.find(c => c.key === key);
        if (existing) {
            existing.qty++;
            existing.lineTotal = existing.qty * existing.unitPrice;
        } else {
            const unitPrice = parseFloat(variant?.price || item.price || item.base_price || 0);
            cart.push({
                key,
                menuItem: item,
                variant,
                qty: 1,
                unitPrice,
                lineTotal: unitPrice,
            });
        }
        renderCart();
    }

    function changeQty(key, delta) {
        const idx = cart.findIndex(c => c.key === key);
        if (idx < 0) return;

        cart[idx].qty += delta;
        if (cart[idx].qty <= 0) {
            cart.splice(idx, 1);
        } else {
            cart[idx].lineTotal = cart[idx].qty * cart[idx].unitPrice;
        }
        renderCart();
    }

    function removeFromCart(key) {
        cart = cart.filter(c => c.key !== key);
        renderCart();
    }

    function clearCart() {
        cart = [];
        currentOrder = null;
        renderCart();
    }

    function renderCart() {
        const container = document.getElementById('cart-items');
        const countEl   = document.getElementById('cart-count');
        const btnClear  = document.getElementById('btn-clear');
        const btnPay    = document.getElementById('btn-pay');

        if (!cart.length) {
            container.innerHTML = '<div class="cart-empty">لا توجد منتجات بعد</div>';
            countEl.textContent = '0 منتج';
            btnClear.disabled = true;
            btnPay.disabled = true;
        } else {
            const totalQty = cart.reduce((s, c) => s + c.qty, 0);
            countEl.textContent = `${totalQty} منتج`;
            btnClear.disabled = false;
            btnPay.disabled = false;

            container.innerHTML = cart.map(c => `
                <div class="cart-item">
                    <div class="cart-qty">
                        <button onclick="changeQty('${c.key}', -1)">−</button>
                        <span>${c.qty}</span>
                        <button onclick="changeQty('${c.key}', 1)">+</button>
                    </div>
                    <div class="cart-item-info">
                        <div class="cart-item-name">${c.menuItem.name}</div>
                        ${c.variant ? `<div class="cart-item-detail">${c.variant.name}</div>` : ''}
                        <div class="flex gap-2 items-center mt-1">
                            <span class="text-xs text-accent cursor-pointer" onclick="openNoteModal('${c.key}')">📝 ملاحظة</span>
                            ${c.notes ? `<span class="text-xs text-muted truncate max-w-[100px]">(${c.notes})</span>` : ''}
                        </div>
                    </div>
                    <div class="cart-item-price">${c.lineTotal.toFixed(2)}</div>
                    <span class="cart-remove" onclick="removeFromCart('${c.key}')">✕</span>
                </div>
            `).join('');
        }

        // Totals
        let subtotal = cart.reduce((s, c) => s + c.lineTotal, 0);
        let discount = 0;

        if (window.currentOrderDiscount) {
            if (window.currentOrderDiscount.type === 'percentage') {
                discount = subtotal * window.currentOrderDiscount.value / 100;
            } else {
                discount = window.currentOrderDiscount.value;
            }
        }

        const taxable = subtotal - discount;
        const tax = taxable * TAX_RATE / 100;
        const total = taxable + tax;


        
        let totalsHtml = `
            <div class="cart-total-row"><span>المجموع الفرعي</span><span class="val">${money(subtotal)}</span></div>
        `;
        if (discount > 0) {
            totalsHtml += `<div class="cart-total-row text-danger"><span>خصم</span><span class="val">-${money(discount)}</span></div>`;
        }
        if (TAX_RATE > 0) {
            totalsHtml += `
                <div class="cart-total-row"><span>الضريبة (${TAX_RATE}%)</span><span class="val">${money(tax)}</span></div>
            `;
        }
        totalsHtml += `
            <div class="cart-total-row grand"><span>الإجمالي</span><span class="val">${money(total)}</span></div>
        `;
        document.querySelector('.cart-totals').innerHTML = totalsHtml;
    }

    function getTotal() {
        let subtotal = cart.reduce((s, c) => s + c.lineTotal, 0);
        let discount = 0;

        if (window.currentOrderDiscount) {
            if (window.currentOrderDiscount.type === 'percentage') {
                discount = subtotal * window.currentOrderDiscount.value / 100;
            } else {
                discount = window.currentOrderDiscount.value;
            }
        }

        const taxable = subtotal - discount;
        return taxable + taxable * TAX_RATE / 100;
    }

    // ═══ Payment ═══
    function showPayModal() {
        if (!cart.length) return;
        document.getElementById('pm-total').textContent = money(getTotal());
        document.getElementById('pay-amount').value = '';
        document.getElementById('change-display').textContent = 'الباقي: 0.00 ج.م';
        document.getElementById('change-display').className = 'change-display zero';
        selectPayMethod('cash');
        document.getElementById('pay-modal').classList.remove('hidden');
    }

    function closePayModal() {
        document.getElementById('pay-modal').classList.add('hidden');
    }

    function selectPayMethod(method) {
        selectedPayMethod = method;
        document.querySelectorAll('.pay-method').forEach(m => {
            m.classList.toggle('active', m.dataset.method === method);
        });
        // Hide cash section for card
        document.getElementById('pay-cash-section').style.display = method === 'cash' ? 'block' : 'none';
    }

    function calcChange() {
        const paid = parseFloat(document.getElementById('pay-amount').value) || 0;
        const total = getTotal();
        const change = paid - total;
        const el = document.getElementById('change-display');

        if (change >= 0) {
            el.textContent = `الباقي: ${change.toFixed(2)} ج.م`;
            el.className = 'change-display positive';
        } else {
            el.textContent = `المتبقي: ${Math.abs(change).toFixed(2)} ج.م`;
            el.className = 'change-display zero';
        }
    }

    async function confirmPayment() {
        const btn = document.getElementById('btn-confirm-pay');
        const total = getTotal();
        let paidAmount = total;

        if (selectedPayMethod === 'cash') {
            paidAmount = parseFloat(document.getElementById('pay-amount').value) || 0;
            if (paidAmount < total) {
                showToast('المبلغ المدفوع أقل من الإجمالي', 'error');
                return;
            }
        }

        if (selectedOrderType?.type === 'delivery' && !selectedCustomer) {
            showToast('يجب اختيار عميل لتوصيل الطلب', 'error');
            openCustomerModal();
            return;
        }

        btn.disabled = true;
        btn.textContent = 'جاري المعالجة...';

        try {
            // 1. Create the order
            const orderRes = await api('/orders', {
                method: 'POST',
                body: {
                    type: selectedOrderType?.type || 'takeaway',
                    source: selectedOrderType?.source || 'pos',
                    customer_id: selectedCustomer?.id || null,
                    customer_name: selectedCustomer?.name || null,
                    customer_phone: selectedCustomer?.phone || null,
                    delivery_address: selectedCustomer?.address || null,
                    tax_rate: TAX_RATE,
                    discount_type: window.currentOrderDiscount?.type || null,
                    discount_value: window.currentOrderDiscount?.value || null,
                },
            });
            currentOrder = orderRes.data;

            // 2. Add items
            for (const c of cart) {
                await api(`/orders/${currentOrder.id}/items`, {
                    method: 'POST',
                    body: {
                        menu_item_id: c.menuItem.id,
                        quantity: c.qty,
                        variant_id: c.variant?.id || null,
                        notes: c.notes || null,
                    },
                });
            }

            // 3. Process payment
            await api(`/orders/${currentOrder.id}/pay`, {
                method: 'POST',
                body: {
                    payments: [{
                        method: selectedPayMethod,
                        amount: paidAmount,
                    }],
                },
            });

            // Success
            closePayModal();
            showToast('تم الدفع بنجاح ✅');
            clearCart();
            selectCustomer(null);
            if (window.currentOrderDiscount) window.currentOrderDiscount = null;

        } catch (err) {
            showToast(err.message || 'فشل في إتمام العملية', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'تأكيد الدفع';
        }
    }

    // ═══ Note Management ═══
    let currentNoteKey = null;
    function openNoteModal(key) {
        currentNoteKey = key;
        const item = cart.find(c => c.key === key);
        document.getElementById('note-item-name').textContent = item.menuItem.name;
        document.getElementById('item-note-input').value = item.notes || '';
        document.getElementById('note-modal').classList.remove('hidden');
    }

    function closeNoteModal() {
        document.getElementById('note-modal').classList.add('hidden');
    }

    function saveItemNote() {
        const item = cart.find(c => c.key === currentNoteKey);
        if (item) {
            item.notes = document.getElementById('item-note-input').value;
            showToast('تم حفظ الملاحظة');
            renderCart();
        }
        closeNoteModal();
    }

    // ═══ Discount Management ═══
    function openDiscountModal() {
        document.getElementById('disc-value').value = '';
        document.getElementById('discount-modal').classList.remove('hidden');
    }

    function closeDiscountModal() {
        document.getElementById('discount-modal').classList.add('hidden');
    }

    async function applyDiscount() {
        const type = document.getElementById('disc-type').value;
        const value = parseFloat(document.getElementById('disc-value').value) || 0;

        if (value <= 0) { showToast('القيمة غير صالحة', 'error'); return; }

        showToast('جاري تطبيق الخصم...');
        closeDiscountModal();
        // In a real app, we might call backend here or just store it for order creation
        // For now, we'll store it in a global state
        window.currentOrderDiscount = { type, value };
        renderCart();
    }

    // ═══ Session Information ═══
    function openSessionModal() {
        document.getElementById('session-modal').classList.remove('hidden');
        showSessionTab('orders');
    }

    function closeSessionModal() {
        document.getElementById('session-modal').classList.add('hidden');
    }

    function showSessionTab(tab) {
        document.getElementById('session-orders-content').classList.toggle('hidden', tab !== 'orders');
        document.getElementById('session-stats-content').classList.toggle('hidden', tab !== 'stats');
        document.getElementById('tab-session-orders').classList.toggle('active', tab === 'orders');
        document.getElementById('tab-session-stats').classList.toggle('active', tab === 'stats');
        
        if (tab === 'orders') fetchSessionOrders();
        else fetchSessionStats();
    }

    async function fetchSessionOrders() {
        document.getElementById('session-orders-loading').classList.remove('hidden');
        document.getElementById('session-orders-list').innerHTML = '';
        try {
            const res = await api(`/orders?drawer_session_id=${window.currentDrawerSessionId}&per_page=50`);
            const list = document.getElementById('session-orders-list');
            list.innerHTML = res.data.map(o => `
                <tr class="hover:bg-bg-primary transition">
                    <td class="p-2 border-b">${o.order_number.split('-').pop()}</td>
                    <td class="p-2 border-b">${new Date(o.created_at).toLocaleTimeString('ar-EG')}</td>
                    <td class="p-2 border-b text-xs">${o.type_label || o.type}</td>
                    <td class="p-2 border-b"><span class="badge-status ${getStatusColor(o.status)}">${o.status_label || o.status}</span></td>
                    <td class="p-2 border-b"><span class="text-xs ${o.payment_status === 'paid' ? 'text-success' : 'text-warning'}">${o.payment_status_label || o.payment_status}</span></td>
                    <td class="p-2 border-b font-bold">${money(o.total)}</td>
                </tr>
            `).join('');
        } catch (err) {
            showToast('فشل تحميل الطلبات', 'error');
        } finally {
            document.getElementById('session-orders-loading').classList.add('hidden');
        }
    }

    async function fetchSessionStats() {
        document.getElementById('session-stats-loading').classList.remove('hidden');
        document.getElementById('session-stats-grid').innerHTML = '';
        try {
            const res = await api(`/drawers/${window.currentDrawerSessionId}/summary`);
            const s = res.data;
            const grid = document.getElementById('session-stats-grid');
            const stats = [
                { label: 'إجمالي المبيعات', val: money(s.cash_sales + s.non_cash_sales), color: 'text-primary' },
                { label: 'مبيعات نقدية', val: money(s.cash_sales), color: 'text-success' },
                { label: 'مبيعات أخرى (بطاقة)', val: money(s.non_cash_sales), color: 'text-accent' },
                { label: 'الرصيد المتوقع بالدرج', val: money(s.expected_cash), color: 'text-warning' },
                { label: 'إيداع نقدي', val: money(s.cash_in), color: 'text-success' },
                { label: 'سحب نقدي', val: money(s.cash_out), color: 'text-danger' },
                { label: 'إجمالي الطلبات', val: s.order_count, color: 'text-primary' },
                { label: 'طلبات مدفوعة', val: s.paid_orders_count, color: 'text-success' },
                { label: 'طلبات معلقة', val: s.pending_orders_count, color: 'text-warning' },
            ];
            grid.innerHTML = stats.map(st => `
                <div class="bg-bg-primary p-3 rounded-lg border border-border">
                    <div class="text-xs text-muted mb-1">${st.label}</div>
                    <div class="text-lg font-bold ${st.color}">${st.val}</div>
                </div>
            `).join('');
        } catch (err) {
            showToast('فشل تحميل الإحصائيات', 'error');
        } finally {
            document.getElementById('session-stats-loading').classList.add('hidden');
        }
    }

    function getStatusColor(status) {
        switch(status) {
            case 'confirmed': return 'badge-secondary';
            case 'preparing': return 'badge-warning';
            case 'ready': return 'badge-success';
            case 'delivered': return 'badge-success';
            case 'cancelled': return 'badge-danger';
            default: return 'badge-ghost';
        }
    }

    // ═══ Cash Movements ═══
    let activeMoveTab = 'in';
    function openMovementModal() {
        document.getElementById('movement-modal').classList.remove('hidden');
        showMoveTab('in');
    }
    function closeMovementModal() {
        document.getElementById('movement-modal').classList.add('hidden');
    }
    function showMoveTab(tab) {
        activeMoveTab = tab;
        document.getElementById('tab-cashin').classList.toggle('active', tab === 'in');
        document.getElementById('tab-cashout').classList.toggle('active', tab === 'out');
        document.getElementById('move-amount').value = '';
        document.getElementById('move-reason').value = '';
    }
    async function processMovement() {
        const amount = parseFloat(document.getElementById('move-amount').value) || 0;
        const reason = document.getElementById('move-reason').value;
        if (amount <= 0) { showToast('المبلغ غير صالح', 'error'); return; }

        const btn = document.getElementById('move-btn');
        btn.disabled = true;
        btn.textContent = 'جاري المعالجة...';

        try {
            await api(`/drawers/${window.currentDrawerSessionId}/${activeMoveTab === 'in' ? 'cash-in' : 'cash-out'}`, {
                method: 'POST',
                body: { amount, notes: reason }
            });
            showToast('تمت العملية بنجاح');
            closeMovementModal();
        } catch (err) {
            showToast(err.message || 'فشل تنفيذ الحركة', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'تأكيد';
        }
    }

    // ═══ Logout ═══
    function logout() {
        api('/auth/logout', { method: 'POST' }).catch(() => {});
        clearAuth();
        window.location.href = '/pos/login';
    }
</script>
@endsection
