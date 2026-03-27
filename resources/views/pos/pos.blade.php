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
    .topbar-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end; }
    .topbar-close-btn {
        border-color: rgba(239, 68, 68, 0.35);
        background: rgba(239, 68, 68, 0.12);
        color: #fca5a5;
        font-weight: 700;
    }
    .topbar-close-btn:hover:not(:disabled) {
        background: rgba(239, 68, 68, 0.2);
        border-color: rgba(239, 68, 68, 0.5);
        color: #fecaca;
    }

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

    .items-toolbar {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg-secondary);
        flex-shrink: 0;
    }
    .items-search {
        width: 100%;
        background: var(--bg-input);
        border: 1px solid var(--border);
        border-radius: 999px;
        color: var(--text-primary);
        padding: 0.75rem 1rem;
        outline: none;
    }
    .items-search:focus {
        border-color: var(--border-focus);
        box-shadow: 0 0 0 3px var(--accent-glow);
    }

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
    .item-meta { font-size: 0.72rem; color: var(--text-muted); }

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
    .pay-card-section {
        margin-bottom: 1rem;
        padding: 0.9rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--bg-card);
    }
    .pay-preview-card {
        margin-top: 0.75rem;
        padding: 0.85rem;
        border-radius: var(--radius);
        background: var(--bg-primary);
        border: 1px solid var(--border);
        display: grid;
        gap: 0.5rem;
    }
    .pay-preview-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.88rem;
    }
    .pay-preview-row strong {
        color: var(--text-primary);
        direction: ltr;
        white-space: nowrap;
    }
    .pay-preview-row.muted strong { color: var(--text-secondary); }
    .pay-preview-row.emphasis {
        padding-top: 0.5rem;
        border-top: 1px solid var(--border);
        font-weight: 700;
    }
    .pay-preview-help {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.5rem;
    }
    .change-display { text-align: center; padding: 0.75rem; border-radius: var(--radius); margin-bottom: 1rem; }
    .change-display.positive { background: var(--success-bg); color: var(--success); }
    .change-display.zero { background: var(--bg-card); color: var(--text-secondary); }
    .pay-shortcuts {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    .pay-shortcut {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--bg-card);
        color: var(--text-primary);
        padding: 0.65rem 0.5rem;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
    }
    .pay-shortcut:hover { background: var(--bg-card-hover); }

    .config-section {
        margin-bottom: 1rem;
        padding: 0.9rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--bg-primary);
    }
    .config-section-title {
        font-size: 0.9rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
    }
    .config-help {
        color: var(--text-secondary);
        font-size: 0.75rem;
        margin-top: -0.3rem;
        margin-bottom: 0.75rem;
    }
    .config-options {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .config-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.8rem 0.9rem;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--bg-card);
        cursor: pointer;
    }
    .config-option:hover { background: var(--bg-card-hover); }
    .config-option-info {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
        flex: 1;
    }
    .config-option input { accent-color: var(--accent); }
    .config-option-price {
        color: var(--accent);
        font-weight: 700;
        direction: ltr;
        white-space: nowrap;
    }
    .config-qty {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: 0.3rem;
    }
    .config-qty button {
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 50%;
        background: var(--bg-card);
        color: var(--text-primary);
        font-size: 1rem;
        cursor: pointer;
    }
    .config-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.9rem 1rem;
        border-radius: var(--radius);
        background: rgba(99, 102, 241, 0.08);
        border: 1px solid rgba(99, 102, 241, 0.18);
        margin-bottom: 1rem;
    }
    .config-summary-price {
        color: var(--accent);
        font-size: 1.2rem;
        font-weight: 800;
        direction: ltr;
    }
    .discount-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: rgba(34, 197, 94, 0.14);
        color: var(--success);
        border-radius: 999px;
        padding: 0.2rem 0.55rem;
        font-size: 0.7rem;
        font-weight: 700;
    }

    @media (max-width: 960px) {
        .pos-container { flex-direction: column; }
        .pos-topbar { flex-wrap: wrap; }
        .topbar-info, .topbar-actions { width: 100%; justify-content: space-between; }
        .type-bar { overflow-x: auto; }
        .type-tab { min-width: 140px; flex: none; }
        .pos-cart {
            width: 100%;
            max-height: 48dvh;
            border-right: none;
            border-top: 1px solid var(--border);
        }
        .pay-shortcuts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
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
                <button class="btn btn-sm btn-secondary topbar-close-btn" onclick="goToCloseDrawer()">
                    <span class="text-sm">🔒 إغلاق الدرج</span>
                </button>
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

        <div class="items-toolbar">
            <input type="search" id="item-search" class="items-search" placeholder="ابحث عن منتج أو صنف...">
        </div>

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
                <span class="text-accent cursor-pointer text-xs font-bold" id="cart-discount-action" onclick="openDiscountModal()">🏷️ خصم</span>
                <span id="cart-discount-state" class="discount-pill hidden"></span>
                <span class="text-sm text-muted" id="cart-count">0 منتج</span>
            </div>
        </div>

        <div class="cart-items" id="cart-items">
            <div class="cart-empty">لا توجد منتجات بعد</div>
        </div>

        <div class="cart-footer">
            <div class="cart-totals">
                <div class="cart-total-row"><span>المجموع الفرعي</span><span class="val" id="t-subtotal">0.00 ج.م</span></div>
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
            <div class="pay-shortcuts" id="pay-shortcuts"></div>
            <div class="change-display zero" id="change-display">الباقي: 0.00 ج.م</div>
        </div>

        <div class="pay-card-section hidden" id="pay-card-section">
            <div class="form-group mb-3">
                <label class="form-label">جهاز الدفع</label>
                <select id="pay-terminal" class="form-input" onchange="handleCardTerminalChange()">
                    <option value="">اختر جهاز الدفع</option>
                </select>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">رقم المرجع / الإيصال</label>
                <input type="text" id="pay-reference-number" class="form-input form-input-lg" placeholder="مثال: 123456 أو RCP-001">
            </div>
            <div class="pay-preview-card">
                <div class="pay-preview-row">
                    <span>المبلغ المدفوع</span>
                    <strong id="card-paid-preview">0.00 ج.م</strong>
                </div>
                <div class="pay-preview-row muted">
                    <span>رسوم الجهاز</span>
                    <strong id="card-fee-preview">—</strong>
                </div>
                <div class="pay-preview-row emphasis">
                    <span>صافي التسوية</span>
                    <strong id="card-net-preview">—</strong>
                </div>
            </div>
            <div class="pay-preview-help" id="card-preview-help">اختر جهاز الدفع لعرض الرسوم وصافي التسوية.</div>
        </div>

        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1" onclick="closePayModal()">إلغاء</button>
            <button class="btn btn-success flex-1" id="btn-confirm-pay" onclick="confirmPayment()" style="flex:2">تأكيد الدفع</button>
        </div>
    </div>
</div>

{{-- ═══ Order Type Modal ═══ --}}
<div id="type-modal" class="modal-overlay hidden" onclick="event.target===this && closeTypeModal()">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-title">🧾 اختر نوع الطلب</div>
        <div class="config-options" id="type-options-list"></div>
        <div class="flex gap-2" style="margin-top:1rem;">
            <button class="btn btn-secondary flex-1" onclick="closeTypeModal()">إغلاق</button>
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

{{-- ═══ Item Config Modal ═══ --}}
<div id="item-config-modal" class="modal-overlay hidden" onclick="event.target===this && closeItemConfigModal()">
    <div class="modal-content" style="max-width:720px; width:95%;">
        <div class="modal-title" id="config-item-title">تخصيص المنتج</div>

        <div class="config-summary">
            <div>
                <div class="text-sm text-muted">الإجمالي الحالي</div>
                <div class="config-summary-price" id="config-total-price">0.00 ج.م</div>
            </div>
            <div class="config-qty">
                <button type="button" onclick="changeConfigQty(-1)">−</button>
                <strong id="config-qty-value">1</strong>
                <button type="button" onclick="changeConfigQty(1)">+</button>
            </div>
        </div>

        <div id="item-config-body"></div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label">ملاحظة الطلب</label>
            <textarea id="config-note-input" class="form-input" rows="3" placeholder="مثلاً: بدون بصل، زيادة شطة..."></textarea>
        </div>

        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1" onclick="closeItemConfigModal()">إلغاء</button>
            <button class="btn btn-primary flex-1" onclick="confirmItemConfig()" style="flex:2">إضافة إلى السلة</button>
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
        <div class="text-sm text-muted mb-4">يتطلب تطبيق الخصم اعتماد مدير أو أدمن باستخدام رمز PIN.</div>
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
        <div class="form-group">
            <label class="form-label">سبب الخصم</label>
            <textarea id="disc-reason" class="form-input" rows="3" maxlength="1000"
                      placeholder="اكتب سبب الخصم ليظهر في سجل الطلب"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">اعتماد بواسطة</label>
            <select id="disc-approver" class="form-input">
                <option value="">اختر المدير / الأدمن</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">رمز اعتماد المدير</label>
            <input type="password" id="disc-approver-pin" class="form-input form-input-lg"
                   readonly onclick="openNumPad('disc-approver-pin', 'رمز اعتماد المدير')" placeholder="••••">
        </div>
        <div id="discount-approval-hint" class="text-xs text-muted mb-4">لن يتم تطبيق الخصم قبل اعتماد المدير.</div>
        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1" onclick="closeDiscountModal()">إلغاء</button>
            <button class="btn btn-primary flex-1" id="discount-apply-btn" onclick="applyDiscount()">اعتماد وتطبيق</button>
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

        <div id="session-stats-restricted" class="hidden text-sm text-muted mb-4 p-3 rounded-lg border border-border bg-bg-primary">
            تظهر الإحصائيات المالية للكاشير فقط أثناء إغلاق الجلسة بعد إدخال المبلغ الفعلي.
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
            <a href="/pos/close-drawer" class="text-danger text-sm font-bold">⚠️ إغلاق الوردية والدرج</a>
        </div>
    </div>
</div>
</div>
@endsection

@section('scripts')
<script>
    if (!requireAuth()) throw 'no-auth';
    if (!canAccessPosSurface()) {
        showToast('ليس لديك صلاحية للوصول إلى نقطة البيع', 'error');
        setTimeout(() => redirectToAuthorizedHome(), 800);
        throw new Error('POS access denied');
    }

    const TAX_RATE = 0;
    const currentUser = getUser() || {};

    let menuData = [];
    let orderTypes = [];
    let selectedOrderType = null;
    let selectedCustomer = null;
    let activeCatId = null;
    let itemSearch = '';
    let cart = [];
    let cartLineCounter = 0;
    let currentOrder = null;
    let currentOrderDiscount = null;
    let selectedPayMethod = 'cash';
    let drawerSession = null;
    let activeShift = null;
    let searchResults = [];
    let customerSearchTimer = null;
    let currentNoteLineId = null;
    let currentConfigItem = null;
    let currentItemConfig = null;
    let activeMoveTab = 'in';
    let discountApprovers = [];
    let paymentTerminals = [];
    let currentCardPreview = null;
    let lastPaidOrder = null;

    function userHasPermission(permission) {
        if (Array.isArray(currentUser.roles) && currentUser.roles.some((role) => role.name === 'admin')) {
            return true;
        }

        return Array.isArray(currentUser.permissions) && currentUser.permissions.includes(permission);
    }

    function userHasRole(role) {
        return Array.isArray(currentUser.roles) && currentUser.roles.some((userRole) => userRole.name === role);
    }

    function isCashierOnlyUser() {
        return userHasRole('cashier') && !userHasRole('admin') && !userHasRole('manager');
    }

    function canViewLiveSessionStats() {
        if (typeof currentUser.can_view_live_session_stats === 'boolean') {
            return currentUser.can_view_live_session_stats;
        }

        return !isCashierOnlyUser();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function moneyValue(amount) {
        return Number.parseFloat(amount || 0).toFixed(2);
    }

    function formatReceiptDateTime(value) {
        try {
            const date = value ? new Date(value) : new Date();
            return new Intl.DateTimeFormat('ar-EG', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        } catch {
            return '';
        }
    }

    function paymentMethodLabel(method) {
        if (method === 'cash') return 'نقدي';
        if (method === 'card') return 'بطاقة';
        return method || '—';
    }

    function openReceiptPrintWindow() {
        return window.open('', '_blank', 'width=420,height=760');
    }

    function escapeReceiptHtml(value) {
        return escapeHtml(value ?? '');
    }

    function buildReceiptHtml(order, { shouldOpenDrawer = false } = {}) {
        const items = Array.isArray(order?.items) ? order.items : [];
        const payments = Array.isArray(order?.payments) ? order.payments : [];
        const primaryPayment = payments[0] || null;
        const customerName = order?.customer_name || order?.customer?.name || 'عميل نقدي';
        const customerPhone = order?.customer_phone || order?.customer?.phone || '';
        const cashierName = order?.cashier?.name || currentUser.name || '—';
        const deviceName = order?.pos_device?.name || order?.posDevice?.name || drawerSession?.pos_device?.name || '—';
        const reference = primaryPayment?.reference_number || '';
        const terminalName = primaryPayment?.terminal?.name || '';

        return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إيصال ${escapeReceiptHtml(order?.order_number || '')}</title>
    <style>
        @page { size: 80mm auto; margin: 6mm; }
        body {
            margin: 0;
            color: #000;
            background: #fff;
            font-family: "Tajawal", Arial, sans-serif;
            direction: rtl;
        }
        .receipt {
            width: 100%;
            max-width: 72mm;
            margin: 0 auto;
            font-size: 12px;
            line-height: 1.45;
        }
        .center { text-align: center; }
        .muted { color: #555; }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 4px 0;
        }
        .row strong:last-child, .item-row strong:last-child { direction: ltr; text-align: left; }
        .item {
            margin: 8px 0;
            padding-bottom: 6px;
            border-bottom: 1px dotted #999;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: flex-start;
        }
        .item-name { font-weight: 700; }
        .item-meta { font-size: 11px; color: #555; margin-top: 2px; }
        .totals .row { font-size: 13px; }
        .grand {
            font-size: 15px;
            font-weight: 800;
        }
        .footer-note {
            margin-top: 10px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            <div class="title">Tarweaa</div>
            <div class="muted">إيصال دفع</div>
        </div>

        <div class="line"></div>

        <div class="row"><span>رقم الطلب</span><strong>${escapeReceiptHtml(order?.order_number || '—')}</strong></div>
        <div class="row"><span>التاريخ</span><strong>${escapeReceiptHtml(formatReceiptDateTime(order?.created_at))}</strong></div>
        <div class="row"><span>الكاشير</span><strong>${escapeReceiptHtml(cashierName)}</strong></div>
        <div class="row"><span>نقطة البيع</span><strong>${escapeReceiptHtml(deviceName)}</strong></div>
        <div class="row"><span>العميل</span><strong>${escapeReceiptHtml(customerName)}</strong></div>
        ${customerPhone ? `<div class="row"><span>الهاتف</span><strong>${escapeReceiptHtml(customerPhone)}</strong></div>` : ''}
        ${order?.delivery_address ? `<div class="row"><span>العنوان</span><strong>${escapeReceiptHtml(order.delivery_address)}</strong></div>` : ''}

        <div class="line"></div>

        ${items.map((item) => {
            const modifiers = Array.isArray(item?.modifiers) ? item.modifiers.map((modifier) => `${modifier.modifier_name} x${modifier.quantity}`).join(' • ') : '';
            const notes = item?.notes ? `ملاحظة: ${item.notes}` : '';
            const metaParts = [item?.variant_name, modifiers, notes].filter(Boolean);
            return `
                <div class="item">
                    <div class="item-row">
                        <div>
                            <div class="item-name">${escapeReceiptHtml(item?.item_name || '—')}</div>
                            ${metaParts.length ? `<div class="item-meta">${escapeReceiptHtml(metaParts.join(' • '))}</div>` : ''}
                        </div>
                        <strong>${escapeReceiptHtml(`${moneyValue(item?.quantity || 0)} x ${moneyValue(item?.unit_price || 0)}`)}</strong>
                    </div>
                    <div class="row">
                        <span></span>
                        <strong>${escapeReceiptHtml(moneyValue(item?.total || 0))} ج.م</strong>
                    </div>
                </div>
            `;
        }).join('')}

        <div class="line"></div>

        <div class="totals">
            <div class="row"><span>المجموع الفرعي</span><strong>${escapeReceiptHtml(moneyValue(order?.subtotal || 0))} ج.م</strong></div>
            <div class="row"><span>الخصم</span><strong>${escapeReceiptHtml(moneyValue(order?.discount_amount || 0))} ج.م</strong></div>
            <div class="row"><span>الضريبة</span><strong>${escapeReceiptHtml(moneyValue(order?.tax_amount || 0))} ج.م</strong></div>
            <div class="row"><span>التوصيل</span><strong>${escapeReceiptHtml(moneyValue(order?.delivery_fee || 0))} ج.م</strong></div>
            <div class="row grand"><span>الإجمالي</span><strong>${escapeReceiptHtml(moneyValue(order?.total || 0))} ج.م</strong></div>
        </div>

        <div class="line"></div>

        ${payments.map((payment) => `
            <div class="row"><span>الدفع (${escapeReceiptHtml(paymentMethodLabel(payment?.payment_method))})</span><strong>${escapeReceiptHtml(moneyValue(payment?.amount || 0))} ج.م</strong></div>
            ${payment?.terminal?.name ? `<div class="row"><span>الجهاز</span><strong>${escapeReceiptHtml(payment.terminal.name)}</strong></div>` : ''}
            ${payment?.reference_number ? `<div class="row"><span>المرجع</span><strong>${escapeReceiptHtml(payment.reference_number)}</strong></div>` : ''}
        `).join('')}
        <div class="row"><span>المبلغ المدفوع</span><strong>${escapeReceiptHtml(moneyValue(order?.paid_amount || 0))} ج.م</strong></div>
        <div class="row"><span>الباقي</span><strong>${escapeReceiptHtml(moneyValue(order?.change_amount || 0))} ج.م</strong></div>

        ${(primaryPayment?.payment_method === 'card' && primaryPayment?.fee_amount) ? `
            <div class="row"><span>رسوم البطاقة</span><strong>${escapeReceiptHtml(moneyValue(primaryPayment.fee_amount))} ج.م</strong></div>
            <div class="row"><span>صافي التسوية</span><strong>${escapeReceiptHtml(moneyValue(primaryPayment.net_settlement_amount || 0))} ج.م</strong></div>
        ` : ''}

        <div class="line"></div>

        <div class="center footer-note">
            <div>شكرًا لزيارتكم</div>
            ${shouldOpenDrawer ? '<div class="muted">سيتم إرسال أمر الطباعة الآن. إذا كانت طابعة الكاش تدعم فتح الدرج مع أمر الطباعة فسيتم فتحه تلقائيًا.</div>' : ''}
            ${terminalName ? `<div class="muted">تمت العملية عبر ${escapeReceiptHtml(terminalName)}</div>` : ''}
        </div>
    </div>
    <script>
        window.onload = () => {
            setTimeout(() => {
                window.focus();
                window.print();
            }, 250);
        };
        window.onafterprint = () => {
            setTimeout(() => window.close(), 250);
        };
    <\/script>
</body>
</html>`;
    }

    async function printPaidOrderReceipt(orderId, { shouldOpenDrawer = false, receiptWindow = null } = {}) {
        const printWindow = receiptWindow || openReceiptPrintWindow();

        if (!printWindow) {
            showToast('تم الدفع، لكن المتصفح منع نافذة الطباعة. اسمح بالنوافذ المنبثقة للطباعة.', 'error');
            return;
        }

        try {
            printWindow.document.write('<html><body style="font-family:Tajawal,Arial,sans-serif;padding:24px;text-align:center">جاري تجهيز الإيصال...</body></html>');
            printWindow.document.close();

            const response = await api(`/orders/${orderId}`);
            const order = response?.data;

            lastPaidOrder = order || null;

            if (!order) {
                throw new Error('تعذر تحميل بيانات الإيصال');
            }

            printWindow.document.open();
            printWindow.document.write(buildReceiptHtml(order, { shouldOpenDrawer }));
            printWindow.document.close();
        } catch (err) {
            printWindow.close();
            showToast(err.message || 'تم الدفع لكن تعذر تجهيز الإيصال للطباعة', 'error');
        }
    }

    function getSubtotal() {
        return cart.reduce((sum, item) => sum + item.lineTotal, 0);
    }

    function getDiscountAmount() {
        const subtotal = getSubtotal();
        if (!currentOrderDiscount || subtotal <= 0) {
            return 0;
        }

        if (currentOrderDiscount.type === 'percentage') {
            return Math.min(subtotal, subtotal * (currentOrderDiscount.value / 100));
        }

        return Math.min(subtotal, currentOrderDiscount.value);
    }

    function getTaxAmount() {
        const taxable = Math.max(0, getSubtotal() - getDiscountAmount());
        return taxable * TAX_RATE / 100;
    }

    function getTotal() {
        return Math.max(0, getSubtotal() - getDiscountAmount()) + getTaxAmount();
    }

    function updateDiscountDisplay() {
        const action = document.getElementById('cart-discount-action');
        const state = document.getElementById('cart-discount-state');

        if (!userHasPermission('apply_discount')) {
            action.classList.add('hidden');
            state.classList.add('hidden');
            return;
        }

        action.classList.remove('hidden');
        action.textContent = currentOrderDiscount ? '🏷️ تعديل الخصم' : '🏷️ خصم';

        if (!currentOrderDiscount) {
            state.classList.add('hidden');
            state.textContent = '';
            return;
        }

        const suffix = currentOrderDiscount.type === 'percentage'
            ? `${currentOrderDiscount.value}%`
            : money(currentOrderDiscount.value);

        const approverLabel = currentOrderDiscount.approver?.name
            ? ` · اعتماد: ${currentOrderDiscount.approver.name}`
            : '';

        state.textContent = `خصم ${suffix}${approverLabel}`;
        state.classList.remove('hidden');
    }

    function updateCustomerDisplay() {
        const el = document.getElementById('tp-customer');
        if (!selectedCustomer) {
            el.textContent = selectedOrderType?.type === 'delivery' ? 'اختر عميل التوصيل' : 'نقدي (تغيير)';
            return;
        }

        const addressFlag = selectedOrderType?.type === 'delivery' && !selectedCustomer.address
            ? ' - بدون عنوان'
            : '';

        el.textContent = `${selectedCustomer.name} (${selectedCustomer.phone})${addressFlag}`;
    }

    function bindUiEvents() {
        document.getElementById('item-search')?.addEventListener('input', (event) => {
            itemSearch = event.target.value.trim().toLowerCase();
            renderItems(activeCatId);
        });

        document.getElementById('pay-amount')?.addEventListener('input', calcChange);

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            [
                ['item-config-modal', closeItemConfigModal],
                ['type-modal', closeTypeModal],
                ['pay-modal', closePayModal],
                ['customer-modal', closeCustomerModal],
                ['note-modal', closeNoteModal],
                ['discount-modal', closeDiscountModal],
                ['session-modal', closeSessionModal],
                ['movement-modal', closeMovementModal],
            ].forEach(([id, closer]) => {
                const modal = document.getElementById(id);
                if (modal && !modal.classList.contains('hidden')) {
                    closer();
                }
            });
        });
    }

    (async function init() {
        bindUiEvents();
        updateDiscountDisplay();

        try {
            const drawerRes = await api('/drawers/active');
            if (!drawerRes?.data) {
                window.location.href = '/pos/drawer';
                return;
            }

            if (drawerRes.data.close_reconciliation?.locked) {
                showToast('تم بدء جرد إغلاق الدرج. أكمل الإغلاق أولاً.', 'info');
                window.location.href = '/pos/close-drawer';
                return;
            }

            window.currentDrawerSessionId = drawerRes.data.id;
            drawerSession = drawerRes.data;

            const statusRes = await api('/pos/status');
            if (statusRes?.data) {
                document.getElementById('tp-cashier').textContent = statusRes.data.cashier?.name || currentUser.name || '—';
                document.getElementById('tp-shift').textContent = statusRes.data.shift?.shift_number || '#' + (statusRes.data.shift?.id || '—');
                activeShift = statusRes.data.shift;
            } else {
                document.getElementById('tp-cashier').textContent = currentUser.name || '—';
            }

            const typeRes = await api('/pos/order-types');
            orderTypes = typeRes?.data || [];
            renderOrderTypes();

            if (userHasPermission('apply_discount')) {
                await loadDiscountApprovers();
            }

            if (orderTypes.length > 0) {
                selectType(orderTypes[0].id, { silent: true, closeModal: false });
            }

            const menuRes = await api('/pos/menu');
            menuData = menuRes?.data || [];
            renderCategories();
            updateCustomerDisplay();
        } catch (err) {
            showToast(err.message || 'خطأ في تحميل البيانات', 'error');
        } finally {
            document.getElementById('pos-loading').classList.add('hidden');
            document.getElementById('pos-app').classList.remove('hidden');
        }
    })();

    function renderOrderTypes() {
        const bar = document.getElementById('type-bar');
        const list = document.getElementById('type-options-list');

        bar.innerHTML = orderTypes.map((type) => `
            <div class="type-tab ${selectedOrderType?.id === type.id ? 'active' : ''}"
                 id="type-tab-${type.id}"
                 onclick="selectType(${type.id})">
                ${escapeHtml(type.name)}
            </div>
        `).join('');

        list.innerHTML = orderTypes.map((type) => `
            <label class="config-option">
                <div class="config-option-info">
                    <input type="radio" name="order-type" ${selectedOrderType?.id === type.id ? 'checked' : ''}
                           onclick="selectType(${type.id})">
                    <div>
                        <div class="font-bold">${escapeHtml(type.name)}</div>
                        <div class="text-xs text-muted">${escapeHtml(type.type)} / ${escapeHtml(type.source)}</div>
                    </div>
                </div>
            </label>
        `).join('');
    }

    function openTypeModal() {
        document.getElementById('type-modal').classList.remove('hidden');
    }

    function closeTypeModal() {
        document.getElementById('type-modal').classList.add('hidden');
    }

    function selectType(id, { silent = false, closeModal = true } = {}) {
        selectedOrderType = orderTypes.find((type) => type.id === id) || null;
        renderOrderTypes();

        if (selectedOrderType) {
            document.getElementById('cart-order-type').textContent = selectedOrderType.name;
            updateCustomerDisplay();

            if (!silent) {
                showToast(`نوع الطلب: ${selectedOrderType.name}`);
            }
        }

        if (closeModal) {
            closeTypeModal();
        }
    }

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

    function searchCustomers() {
        clearTimeout(customerSearchTimer);
        customerSearchTimer = setTimeout(runCustomerSearch, 250);
    }

    async function runCustomerSearch() {
        const search = document.getElementById('cust-search-input').value.trim();
        const results = document.getElementById('cust-results');

        if (search.length < 2) {
            results.innerHTML = '<div class="text-center p-4 text-muted">اكتب حرفين على الأقل للبحث...</div>';
            return;
        }

        try {
            const res = await api(`/pos/customers?search=${encodeURIComponent(search)}`);
            searchResults = res?.data || [];

            if (!searchResults.length) {
                results.innerHTML = '<div class="text-center p-4 text-muted">لا يوجد نتائج</div>';
                return;
            }

            results.innerHTML = searchResults.map((customer, index) => `
                <div class="p-3 border-b cursor-pointer hover-bg" onclick="selectCustomerByIndex(${index})">
                    <div class="font-bold">${escapeHtml(customer.name)}</div>
                    <div class="text-sm text-secondary">${escapeHtml(customer.phone || '—')}</div>
                    ${customer.address ? `<div class="text-xs text-muted mt-1">${escapeHtml(customer.address)}</div>` : ''}
                </div>
            `).join('');
        } catch (err) {
            results.innerHTML = '<div class="text-center p-4 text-danger">فشل البحث عن العملاء</div>';
        }
    }

    function selectCustomerByIndex(index) {
        selectCustomer(searchResults[index]);
    }

    function selectCustomer(customer) {
        selectedCustomer = customer;
        updateCustomerDisplay();

        if (customer) {
            showToast(`تم اختيار العميل: ${customer.name}`);
        }

        closeCustomerModal();
    }

    async function createNewCustomer() {
        const name = document.getElementById('nc-name').value.trim();
        const phone = document.getElementById('nc-phone').value.trim();
        const address = document.getElementById('nc-address').value.trim();

        if (!name || !phone) {
            showToast('الاسم ورقم الهاتف مطلوبان', 'error');
            return;
        }

        if (selectedOrderType?.type === 'delivery' && !address) {
            showToast('عنوان التوصيل مطلوب لطلبات التوصيل', 'error');
            return;
        }

        try {
            const res = await api('/pos/customers', {
                method: 'POST',
                body: { name, phone, address },
            });

            selectCustomer(res.data);
            document.getElementById('nc-name').value = '';
            document.getElementById('nc-phone').value = '';
            document.getElementById('nc-address').value = '';
        } catch (err) {
            showToast(err.message || 'خطأ في إضافة العميل', 'error');
        }
    }

    function renderCategories() {
        const bar = document.getElementById('cat-bar');
        bar.innerHTML = '';

        const allBtn = document.createElement('button');
        allBtn.className = `cat-btn ${activeCatId === null ? 'active' : ''}`;
        allBtn.textContent = 'الكل';
        allBtn.onclick = () => selectCategory(null, allBtn);
        bar.appendChild(allBtn);

        menuData.forEach((category) => {
            const btn = document.createElement('button');
            btn.className = `cat-btn ${activeCatId === category.id ? 'active' : ''}`;
            btn.textContent = category.name;
            btn.onclick = () => selectCategory(category.id, btn);
            bar.appendChild(btn);
        });

        renderItems(activeCatId);
    }

    function selectCategory(categoryId, btn) {
        activeCatId = categoryId;
        document.querySelectorAll('.cat-btn').forEach((item) => item.classList.remove('active'));
        btn.classList.add('active');
        renderItems(categoryId);
    }

    function getVisibleItems(categoryId) {
        let items = [];

        if (categoryId === null) {
            menuData.forEach((category) => {
                (category.menu_items || []).forEach((item) => items.push(item));
            });
        } else {
            const category = menuData.find((item) => item.id === categoryId);
            items = category?.menu_items || [];
        }

        if (!itemSearch) {
            return items;
        }

        return items.filter((item) => {
            const haystack = `${item.name} ${item.description || ''}`.toLowerCase();
            return haystack.includes(itemSearch);
        });
    }

    function renderItems(categoryId) {
        const grid = document.getElementById('items-grid');
        const items = getVisibleItems(categoryId);

        if (!items.length) {
            grid.innerHTML = `<div class="cart-empty">${itemSearch ? 'لا توجد نتائج مطابقة للبحث' : 'لا توجد منتجات'}</div>`;
            return;
        }

        grid.innerHTML = items.map((item) => {
            const variants = Array.isArray(item.variants) ? item.variants : [];
            const firstVariantPrice = variants.length ? Number.parseFloat(variants[0].price || 0) : null;
            const basePrice = firstVariantPrice ?? Number.parseFloat(item.price || item.base_price || 0);
            const hasOptions = itemRequiresConfiguration(item);

            return `
                <div class="item-card" onclick="addToCartById(${item.id})">
                    <div class="item-name">${escapeHtml(item.name)}</div>
                    <div class="item-price">${moneyValue(basePrice)} ج.م</div>
                    <div class="item-meta">${hasOptions ? 'خيارات متاحة' : 'إضافة مباشرة'}</div>
                </div>
            `;
        }).join('');
    }

    function findMenuItemById(itemId) {
        for (const category of menuData) {
            const item = (category.menu_items || []).find((menuItem) => menuItem.id === itemId);
            if (item) {
                return item;
            }
        }

        return null;
    }

    function itemRequiresConfiguration(item) {
        const variants = Array.isArray(item.variants) ? item.variants : [];
        const modifierGroups = Array.isArray(item.modifier_groups) ? item.modifier_groups : [];

        return variants.length > 1 || modifierGroups.length > 0;
    }

    function getDefaultVariant(item) {
        return Array.isArray(item.variants) && item.variants.length ? item.variants[0] : null;
    }

    function buildModifierSelection(groups) {
        const selection = {};

        (groups || []).forEach((group) => {
            const modifiers = Array.isArray(group.modifiers) ? group.modifiers : [];

            if (group.is_required && group.selection_type === 'single' && modifiers.length) {
                selection[modifiers[0].id] = 1;
            }
        });

        return selection;
    }

    function addToCartById(itemId) {
        const item = findMenuItemById(itemId);
        if (item) {
            addToCart(item);
        }
    }

    function addToCart(item) {
        if (itemRequiresConfiguration(item)) {
            openItemConfigModal(item);
            return;
        }

        const variant = getDefaultVariant(item);
        addConfiguredItem(item, {
            quantity: 1,
            variantId: variant?.id || null,
            modifiers: {},
            notes: '',
        });
    }

    function openItemConfigModal(item) {
        currentConfigItem = item;
        currentItemConfig = {
            quantity: 1,
            variantId: getDefaultVariant(item)?.id || null,
            modifiers: buildModifierSelection(item.modifier_groups),
            notes: '',
        };

        document.getElementById('config-item-title').textContent = item.name;
        document.getElementById('config-note-input').value = '';
        renderItemConfigBody();
        updateConfigSummary();
        document.getElementById('item-config-modal').classList.remove('hidden');
    }

    function closeItemConfigModal() {
        document.getElementById('item-config-modal').classList.add('hidden');
        currentConfigItem = null;
        currentItemConfig = null;
    }

    function renderItemConfigBody() {
        if (!currentConfigItem || !currentItemConfig) {
            return;
        }

        const body = document.getElementById('item-config-body');
        const sections = [];
        const variants = Array.isArray(currentConfigItem.variants) ? currentConfigItem.variants : [];
        const modifierGroups = Array.isArray(currentConfigItem.modifier_groups) ? currentConfigItem.modifier_groups : [];

        if (variants.length) {
            sections.push(`
                <div class="config-section">
                    <div class="config-section-title">الحجم / النوع</div>
                    <div class="config-options">
                        ${variants.map((variant) => `
                            <label class="config-option">
                                <div class="config-option-info">
                                    <input type="radio"
                                           name="config-variant"
                                           ${currentItemConfig.variantId === variant.id ? 'checked' : ''}
                                           onclick="setConfigVariant(${variant.id})">
                                    <div>${escapeHtml(variant.name)}</div>
                                </div>
                                <div class="config-option-price">${money(variant.price)}</div>
                            </label>
                        `).join('')}
                    </div>
                </div>
            `);
        }

        modifierGroups.forEach((group) => {
            const modifiers = Array.isArray(group.modifiers) ? group.modifiers : [];
            const selectionType = group.selection_type === 'single' ? 'radio' : 'checkbox';

            sections.push(`
                <div class="config-section">
                    <div class="config-section-title">${escapeHtml(group.name)}</div>
                    <div class="config-help">
                        ${group.is_required ? 'اختيار مطلوب' : 'اختياري'}
                        ${group.max_selections ? ` - حتى ${group.max_selections} اختيار` : ''}
                    </div>
                    <div class="config-options">
                        ${modifiers.map((modifier) => `
                            <label class="config-option">
                                <div class="config-option-info">
                                    <input type="${selectionType}"
                                           name="modifier-group-${group.id}"
                                           ${currentItemConfig.modifiers[modifier.id] ? 'checked' : ''}
                                           onclick="toggleModifier(${group.id}, ${modifier.id}, '${group.selection_type}', this.checked)">
                                    <div>${escapeHtml(modifier.name)}</div>
                                </div>
                                <div class="config-option-price">${modifier.price > 0 ? `+ ${money(modifier.price)}` : 'مجاني'}</div>
                            </label>
                        `).join('')}
                    </div>
                </div>
            `);
        });

        body.innerHTML = sections.join('');
    }

    function setConfigVariant(variantId) {
        currentItemConfig.variantId = variantId;
        updateConfigSummary();
    }

    function toggleModifier(groupId, modifierId, selectionType, checked) {
        const group = (currentConfigItem?.modifier_groups || []).find((item) => item.id === groupId);
        const groupModifierIds = (group?.modifiers || []).map((item) => item.id);

        if (selectionType === 'single') {
            groupModifierIds.forEach((id) => delete currentItemConfig.modifiers[id]);
            if (checked) {
                currentItemConfig.modifiers[modifierId] = 1;
            }
        } else if (checked) {
            if (group?.max_selections) {
                const selectedInGroup = groupModifierIds.filter((id) => currentItemConfig.modifiers[id]).length;
                if (!currentItemConfig.modifiers[modifierId] && selectedInGroup >= group.max_selections) {
                    showToast(`الحد الأقصى لمجموعة ${group.name} هو ${group.max_selections}`, 'error');
                    renderItemConfigBody();
                    return;
                }
            }

            currentItemConfig.modifiers[modifierId] = 1;
        } else {
            delete currentItemConfig.modifiers[modifierId];
        }

        updateConfigSummary();
    }

    function changeConfigQty(delta) {
        if (!currentItemConfig) {
            return;
        }

        currentItemConfig.quantity = Math.max(1, currentItemConfig.quantity + delta);
        updateConfigSummary();
    }

    function getSelectedModifierEntries(item, modifierMap) {
        const entries = [];

        (item.modifier_groups || []).forEach((group) => {
            (group.modifiers || []).forEach((modifier) => {
                if (modifierMap[modifier.id]) {
                    entries.push({
                        id: modifier.id,
                        name: modifier.name,
                        price: Number.parseFloat(modifier.price || 0),
                        quantity: modifierMap[modifier.id],
                    });
                }
            });
        });

        return entries;
    }

    function getConfigUnitPrice(item, config) {
        const selectedVariant = (item.variants || []).find((variant) => variant.id === config.variantId) || null;
        const basePrice = selectedVariant
            ? Number.parseFloat(selectedVariant.price || 0)
            : Number.parseFloat(item.price || item.base_price || 0);

        const modifiersTotal = getSelectedModifierEntries(item, config.modifiers)
            .reduce((sum, modifier) => sum + (modifier.price * modifier.quantity), 0);

        return {
            basePrice,
            modifiersTotal,
            unitPrice: basePrice + modifiersTotal,
            variant: selectedVariant,
        };
    }

    function validateCurrentConfig() {
        const groups = currentConfigItem?.modifier_groups || [];

        for (const group of groups) {
            const selectedCount = (group.modifiers || []).filter((modifier) => currentItemConfig.modifiers[modifier.id]).length;

            if (group.is_required && selectedCount < Math.max(1, group.min_selections || 1)) {
                showToast(`يرجى اختيار ${group.name}`, 'error');
                return false;
            }

            if (group.max_selections && selectedCount > group.max_selections) {
                showToast(`تجاوزت الحد الأقصى لمجموعة ${group.name}`, 'error');
                return false;
            }
        }

        return true;
    }

    function updateConfigSummary() {
        if (!currentConfigItem || !currentItemConfig) {
            return;
        }

        const summary = getConfigUnitPrice(currentConfigItem, currentItemConfig);
        const total = summary.unitPrice * currentItemConfig.quantity;

        document.getElementById('config-qty-value').textContent = currentItemConfig.quantity;
        document.getElementById('config-total-price').textContent = money(total);
    }

    function buildCartSignature(itemId, variantId, modifiers, notes) {
        const modifiersKey = modifiers
            .map((modifier) => `${modifier.id}:${modifier.quantity}`)
            .sort()
            .join('|');

        return [itemId, variantId || 0, modifiersKey, (notes || '').trim()].join('::');
    }

    function addConfiguredItem(item, config) {
        const summary = getConfigUnitPrice(item, config);
        const modifiers = getSelectedModifierEntries(item, config.modifiers);
        const notes = (config.notes || '').trim();
        const signature = buildCartSignature(item.id, summary.variant?.id || null, modifiers, notes);

        const existing = cart.find((line) => line.signature === signature);
        if (existing) {
            existing.qty += config.quantity;
            existing.lineTotal = existing.qty * existing.unitPrice;
        } else {
            cart.push({
                lineId: ++cartLineCounter,
                signature,
                menuItem: item,
                variant: summary.variant,
                modifiers,
                qty: config.quantity,
                unitPrice: summary.unitPrice,
                lineTotal: summary.unitPrice * config.quantity,
                notes,
            });
        }

        renderCart();
        closeItemConfigModal();
        showToast(`تمت إضافة ${item.name}`);
    }

    function confirmItemConfig() {
        if (!currentConfigItem || !currentItemConfig) {
            return;
        }

        if (!validateCurrentConfig()) {
            return;
        }

        currentItemConfig.notes = document.getElementById('config-note-input').value.trim();
        addConfiguredItem(currentConfigItem, currentItemConfig);
    }

    function changeQty(lineId, delta) {
        const line = cart.find((item) => item.lineId === lineId);
        if (!line) {
            return;
        }

        line.qty += delta;
        if (line.qty <= 0) {
            cart = cart.filter((item) => item.lineId !== lineId);
        } else {
            line.lineTotal = line.qty * line.unitPrice;
        }

        renderCart();
    }

    function removeFromCart(lineId) {
        cart = cart.filter((item) => item.lineId !== lineId);
        renderCart();
    }

    function clearCart() {
        cart = [];
        currentOrder = null;
        currentOrderDiscount = null;
        renderCart();
    }

    function renderCart() {
        const container = document.getElementById('cart-items');
        const countEl = document.getElementById('cart-count');
        const btnClear = document.getElementById('btn-clear');
        const btnPay = document.getElementById('btn-pay');

        if (!cart.length) {
            container.innerHTML = '<div class="cart-empty">لا توجد منتجات بعد</div>';
            countEl.textContent = '0 منتج';
            btnClear.disabled = true;
            btnPay.disabled = true;
        } else {
            const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
            countEl.textContent = `${totalQty} منتج`;
            btnClear.disabled = false;
            btnPay.disabled = false;

            container.innerHTML = cart.map((item) => {
                const details = [
                    item.variant?.name ? escapeHtml(item.variant.name) : '',
                    ...(item.modifiers || []).map((modifier) => escapeHtml(modifier.name)),
                ].filter(Boolean);

                return `
                    <div class="cart-item">
                        <div class="cart-qty">
                            <button onclick="changeQty(${item.lineId}, -1)">−</button>
                            <span>${item.qty}</span>
                            <button onclick="changeQty(${item.lineId}, 1)">+</button>
                        </div>
                        <div class="cart-item-info">
                            <div class="cart-item-name">${escapeHtml(item.menuItem.name)}</div>
                            ${details.length ? `<div class="cart-item-detail">${details.join(' • ')}</div>` : ''}
                            <div class="flex gap-2 items-center mt-1">
                                <span class="text-xs text-accent cursor-pointer" onclick="openNoteModal(${item.lineId})">📝 ملاحظة</span>
                                ${item.notes ? `<span class="text-xs text-muted truncate max-w-[100px]">(${escapeHtml(item.notes)})</span>` : ''}
                            </div>
                        </div>
                        <div class="cart-item-price">${money(item.lineTotal)}</div>
                        <span class="cart-remove" onclick="removeFromCart(${item.lineId})">✕</span>
                    </div>
                `;
            }).join('');
        }

        const subtotal = getSubtotal();
        const discount = getDiscountAmount();
        const tax = getTaxAmount();
        const total = getTotal();

        let totalsHtml = `<div class="cart-total-row"><span>المجموع الفرعي</span><span class="val">${money(subtotal)}</span></div>`;

        if (discount > 0) {
            totalsHtml += `<div class="cart-total-row text-danger"><span>خصم</span><span class="val">- ${money(discount)}</span></div>`;
        }

        if (TAX_RATE > 0) {
            totalsHtml += `<div class="cart-total-row"><span>الضريبة (${TAX_RATE}%)</span><span class="val">${money(tax)}</span></div>`;
        }

        totalsHtml += `<div class="cart-total-row grand"><span>الإجمالي</span><span class="val">${money(total)}</span></div>`;
        document.querySelector('.cart-totals').innerHTML = totalsHtml;
        updateDiscountDisplay();
    }

    function buildPayShortcutAmounts(total) {
        const rounded5 = Math.ceil(total / 5) * 5;
        const rounded10 = Math.ceil(total / 10) * 10;
        const rounded20 = Math.ceil(total / 20) * 20;

        return [total, rounded5, rounded10, rounded20]
            .filter((amount, index, array) => amount > 0 && array.indexOf(amount) === index);
    }

    function renderPayShortcuts() {
        const shortcuts = document.getElementById('pay-shortcuts');
        const total = getTotal();

        shortcuts.innerHTML = buildPayShortcutAmounts(total).map((amount, index) => `
            <button type="button" class="pay-shortcut" onclick="setPayAmount(${amount})">
                ${index === 0 ? 'المطلوب' : money(amount)}
            </button>
        `).join('');
    }

    function setPayAmount(amount) {
        const payInput = document.getElementById('pay-amount');
        payInput.value = moneyValue(amount);
        payInput.dispatchEvent(new Event('input'));
    }

    function resetCardPaymentPreview() {
        currentCardPreview = null;
        document.getElementById('card-paid-preview').textContent = money(getTotal());
        document.getElementById('card-fee-preview').textContent = '—';
        document.getElementById('card-net-preview').textContent = '—';
        document.getElementById('card-preview-help').textContent = 'اختر جهاز الدفع لعرض الرسوم وصافي التسوية.';
    }

    function resetCardPaymentForm() {
        document.getElementById('pay-terminal').value = '';
        document.getElementById('pay-reference-number').value = '';
        resetCardPaymentPreview();
    }

    async function loadPaymentTerminals(force = false) {
        if (!force && paymentTerminals.length) {
            renderPaymentTerminals();
            return paymentTerminals;
        }

        const response = await api('/pos/payment-terminals');
        paymentTerminals = response?.data || [];
        renderPaymentTerminals();

        return paymentTerminals;
    }

    function renderPaymentTerminals() {
        const select = document.getElementById('pay-terminal');
        if (!select) {
            return;
        }

        const currentValue = select.value;

        select.innerHTML = `
            <option value="">اختر جهاز الدفع</option>
            ${paymentTerminals.map((terminal) => `
                <option value="${terminal.id}">
                    ${escapeHtml(terminal.name)}${terminal.bank_name ? ` - ${escapeHtml(terminal.bank_name)}` : ''}
                </option>
            `).join('')}
        `;

        if (currentValue && paymentTerminals.some((terminal) => String(terminal.id) === currentValue)) {
            select.value = currentValue;
        }
    }

    async function refreshCardPaymentPreview() {
        const terminalId = Number.parseInt(document.getElementById('pay-terminal').value || '0', 10);

        document.getElementById('card-paid-preview').textContent = money(getTotal());

        if (!terminalId) {
            resetCardPaymentPreview();
            return;
        }

        try {
            const response = await api('/pos/payment-preview', {
                method: 'POST',
                body: {
                    method: 'card',
                    amount: getTotal(),
                    terminal_id: terminalId,
                },
            });

            currentCardPreview = response.data;
            document.getElementById('card-paid-preview').textContent = money(response.data.paid_amount);
            document.getElementById('card-fee-preview').textContent = money(response.data.fee_amount);
            document.getElementById('card-net-preview').textContent = money(response.data.net_settlement_amount);
            document.getElementById('card-preview-help').textContent = 'الرسوم وصافي التسوية محسوبان من إعدادات الجهاز في النظام.';
        } catch (err) {
            currentCardPreview = null;
            document.getElementById('card-fee-preview').textContent = '—';
            document.getElementById('card-net-preview').textContent = '—';
            document.getElementById('card-preview-help').textContent = err.message || 'تعذر حساب رسوم البطاقة حالياً.';
        }
    }

    function handleCardTerminalChange() {
        refreshCardPaymentPreview();
    }

    function showPayModal() {
        if (!cart.length) {
            return;
        }

        document.getElementById('pm-total').textContent = money(getTotal());
        document.getElementById('change-display').textContent = 'الباقي: 0.00 ج.م';
        document.getElementById('change-display').className = 'change-display zero';
        resetCardPaymentForm();
        renderPayShortcuts();
        selectPayMethod('cash');
        document.getElementById('pay-modal').classList.remove('hidden');
    }

    function closePayModal() {
        document.getElementById('pay-modal').classList.add('hidden');
    }

    function selectPayMethod(method) {
        selectedPayMethod = method;
        document.querySelectorAll('.pay-method').forEach((item) => {
            item.classList.toggle('active', item.dataset.method === method);
        });

        document.getElementById('pay-cash-section').style.display = method === 'cash' ? 'block' : 'none';
        document.getElementById('pay-card-section').classList.toggle('hidden', method !== 'card');

        if (method === 'cash') {
            renderPayShortcuts();
            setPayAmount(getTotal());
            return;
        }

        document.getElementById('card-paid-preview').textContent = money(getTotal());
        loadPaymentTerminals().then(() => refreshCardPaymentPreview()).catch((err) => {
            showToast(err.message || 'تعذر تحميل أجهزة الدفع', 'error');
        });
    }

    function calcChange() {
        const paid = Number.parseFloat(document.getElementById('pay-amount').value) || 0;
        const total = getTotal();
        const change = paid - total;
        const el = document.getElementById('change-display');

        if (change >= 0) {
            el.textContent = `الباقي: ${moneyValue(change)} ج.م`;
            el.className = 'change-display positive';
        } else {
            el.textContent = `المتبقي: ${moneyValue(Math.abs(change))} ج.م`;
            el.className = 'change-display zero';
        }
    }

    function validateDeliveryRequirements() {
        if (selectedOrderType?.type !== 'delivery') {
            return true;
        }

        if (!selectedCustomer) {
            showToast('يجب اختيار عميل لتوصيل الطلب', 'error');
            openCustomerModal();
            return false;
        }

        if (!selectedCustomer.address) {
            showToast('عنوان التوصيل مطلوب للعميل المختار', 'error');
            openCustomerModal();
            return false;
        }

        return true;
    }

    async function confirmPayment() {
        if (!validateDeliveryRequirements()) {
            return;
        }

        const btn = document.getElementById('btn-confirm-pay');
        const total = getTotal();
        let paidAmount = total;
        let receiptWindow = null;
        let paymentPayload = {
            method: selectedPayMethod,
            amount: paidAmount,
        };

        if (selectedPayMethod === 'cash') {
            paidAmount = Number.parseFloat(document.getElementById('pay-amount').value) || 0;
            if (paidAmount < total) {
                showToast('المبلغ المدفوع أقل من الإجمالي', 'error');
                return;
            }

            paymentPayload.amount = paidAmount;
        } else if (selectedPayMethod === 'card') {
            const terminalId = Number.parseInt(document.getElementById('pay-terminal').value || '0', 10);
            const referenceNumber = document.getElementById('pay-reference-number').value.trim();

            if (!terminalId) {
                showToast('اختر جهاز الدفع قبل تأكيد الدفع بالبطاقة', 'error');
                return;
            }

            if (!referenceNumber) {
                showToast('أدخل رقم المرجع أو الإيصال قبل تأكيد الدفع', 'error');
                return;
            }

            await refreshCardPaymentPreview();

            if (!currentCardPreview) {
                showToast('تعذر التحقق من رسوم البطاقة حالياً', 'error');
                return;
            }

            paymentPayload = {
                method: selectedPayMethod,
                amount: total,
                terminal_id: terminalId,
                reference_number: referenceNumber,
            };
        }

        receiptWindow = openReceiptPrintWindow();

        btn.disabled = true;
        btn.textContent = 'جاري المعالجة...';

        try {
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
                },
            });

            currentOrder = orderRes.data;

            for (const item of cart) {
                const modifiers = Object.fromEntries((item.modifiers || []).map((modifier) => [modifier.id, modifier.quantity]));

                await api(`/orders/${currentOrder.id}/items`, {
                    method: 'POST',
                    body: {
                        menu_item_id: item.menuItem.id,
                        quantity: item.qty,
                        variant_id: item.variant?.id || null,
                        modifiers,
                        notes: item.notes || null,
                    },
                });
            }

            if (currentOrderDiscount) {
                await api(`/orders/${currentOrder.id}/discount`, {
                    method: 'POST',
                    body: {
                        type: currentOrderDiscount.type,
                        value: currentOrderDiscount.value,
                        reason: currentOrderDiscount.reason,
                        approval_token: currentOrderDiscount.approval_token,
                    },
                });
            }

            await api(`/orders/${currentOrder.id}/pay`, {
                method: 'POST',
                body: {
                    payments: [paymentPayload],
                },
            });

            await printPaidOrderReceipt(currentOrder.id, {
                shouldOpenDrawer: selectedPayMethod === 'cash',
                receiptWindow,
            });

            closePayModal();
            showToast('تم الدفع بنجاح ✅');
            clearCart();
            selectCustomer(null);
        } catch (err) {
            if (receiptWindow && !receiptWindow.closed) {
                receiptWindow.close();
            }
            showToast(err.message || 'فشل في إتمام العملية', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'تأكيد الدفع';
        }
    }

    function openNoteModal(lineId) {
        currentNoteLineId = lineId;
        const line = cart.find((item) => item.lineId === lineId);
        if (!line) {
            return;
        }

        document.getElementById('note-item-name').textContent = line.menuItem.name;
        document.getElementById('item-note-input').value = line.notes || '';
        document.getElementById('note-modal').classList.remove('hidden');
    }

    function closeNoteModal() {
        document.getElementById('note-modal').classList.add('hidden');
    }

    function saveItemNote() {
        const line = cart.find((item) => item.lineId === currentNoteLineId);
        if (line) {
            line.notes = document.getElementById('item-note-input').value.trim();
            line.signature = buildCartSignature(line.menuItem.id, line.variant?.id || null, line.modifiers || [], line.notes);
            showToast('تم حفظ الملاحظة');
            renderCart();
        }

        closeNoteModal();
    }

    function openDiscountModal() {
        if (!userHasPermission('apply_discount')) {
            showToast('ليس لديك صلاحية لتطبيق الخصم', 'error');
            return;
        }

        if (!cart.length) {
            showToast('أضف منتجات أولاً قبل تطبيق الخصم', 'error');
            return;
        }

        prepareDiscountModal().catch((err) => {
            showToast(err.message || 'تعذر تحميل بيانات اعتماد الخصم', 'error');
        });
    }

    function closeDiscountModal() {
        document.getElementById('discount-modal').classList.add('hidden');
        document.getElementById('disc-approver-pin').value = '';
    }

    async function prepareDiscountModal() {
        await loadDiscountApprovers();

        if (!discountApprovers.length) {
            showToast('لا يوجد مدير أو أدمن متاح لاعتماد الخصم', 'error');
            return;
        }

        document.getElementById('disc-type').value = currentOrderDiscount?.type || 'percentage';
        document.getElementById('disc-value').value = currentOrderDiscount ? moneyValue(currentOrderDiscount.value) : '';
        document.getElementById('disc-reason').value = currentOrderDiscount?.reason || '';
        document.getElementById('disc-approver').value = String(currentOrderDiscount?.approver?.id || '');
        document.getElementById('disc-approver-pin').value = '';
        document.getElementById('discount-modal').classList.remove('hidden');
    }

    async function loadDiscountApprovers(force = false) {
        if (!force && discountApprovers.length) {
            renderDiscountApproverOptions();
            return discountApprovers;
        }

        const response = await api('/pos/discount-approvers');
        discountApprovers = response?.data || [];
        renderDiscountApproverOptions();

        return discountApprovers;
    }

    function renderDiscountApproverOptions() {
        const select = document.getElementById('disc-approver');
        if (!select) {
            return;
        }

        const previousValue = select.value;

        select.innerHTML = `
            <option value="">اختر المدير / الأدمن</option>
            ${discountApprovers.map((approver) => `
                <option value="${approver.id}">
                    ${escapeHtml(approver.name)}${approver.username ? ` (${escapeHtml(approver.username)})` : ''}
                </option>
            `).join('')}
        `;

        if (previousValue && discountApprovers.some((approver) => String(approver.id) === previousValue)) {
            select.value = previousValue;
        }
    }

    async function applyDiscount() {
        const type = document.getElementById('disc-type').value;
        const value = Number.parseFloat(document.getElementById('disc-value').value) || 0;
        const reason = document.getElementById('disc-reason').value.trim();
        const approverId = Number.parseInt(document.getElementById('disc-approver').value || '0', 10);
        const approverPin = document.getElementById('disc-approver-pin').value.trim();
        const btn = document.getElementById('discount-apply-btn');

        if (value <= 0) {
            showToast('القيمة غير صالحة', 'error');
            return;
        }

        if (type === 'percentage' && value > 100) {
            showToast('النسبة لا يمكن أن تتجاوز 100%', 'error');
            return;
        }

        if (type === 'fixed' && value > getSubtotal()) {
            showToast('الخصم الثابت لا يمكن أن يتجاوز المجموع الفرعي', 'error');
            return;
        }

        if (!approverId) {
            showToast('اختر المدير أو الأدمن لاعتماد الخصم', 'error');
            return;
        }

        if (!reason) {
            showToast('اكتب سبب الخصم قبل طلب الاعتماد', 'error');
            return;
        }

        if (!approverPin) {
            showToast('أدخل رمز اعتماد المدير', 'error');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'جاري الاعتماد...';

        try {
            const approvalRes = await api('/pos/discount-approval', {
                method: 'POST',
                body: {
                    type,
                    value,
                    reason,
                    approver_id: approverId,
                    approver_pin: approverPin,
                },
            });

            currentOrderDiscount = {
                type,
                value,
                reason,
                approval_token: approvalRes.data.approval_token,
                approver: approvalRes.data.approver,
            };

            closeDiscountModal();
            renderCart();
            showToast(`تم اعتماد الخصم بواسطة ${approvalRes.data.approver.name}`);
        } catch (err) {
            showToast(err.message || 'فشل اعتماد الخصم', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'اعتماد وتطبيق';
        }
    }

    function openSessionModal() {
        const statsTab = document.getElementById('tab-session-stats');
        const restrictionNotice = document.getElementById('session-stats-restricted');

        statsTab.classList.toggle('hidden', !canViewLiveSessionStats());
        restrictionNotice.classList.toggle('hidden', canViewLiveSessionStats());
        document.getElementById('session-modal').classList.remove('hidden');
        showSessionTab('orders');
    }

    function closeSessionModal() {
        document.getElementById('session-modal').classList.add('hidden');
    }

    function showSessionTab(tab) {
        if (tab === 'stats' && !canViewLiveSessionStats()) {
            showToast('الإحصائيات المالية تظهر عند إغلاق الجلسة بعد الجرد', 'info');
            tab = 'orders';
        }

        document.getElementById('session-orders-content').classList.toggle('hidden', tab !== 'orders');
        document.getElementById('session-stats-content').classList.toggle('hidden', tab !== 'stats');
        document.getElementById('tab-session-orders').classList.toggle('active', tab === 'orders');
        document.getElementById('tab-session-stats').classList.toggle('active', tab === 'stats');

        if (tab === 'orders') {
            fetchSessionOrders();
        } else {
            fetchSessionStats();
        }
    }

    async function fetchSessionOrders() {
        document.getElementById('session-orders-loading').classList.remove('hidden');
        document.getElementById('session-orders-list').innerHTML = '';

        try {
            const res = await api(`/orders?drawer_session_id=${window.currentDrawerSessionId}&per_page=50`);
            const list = document.getElementById('session-orders-list');
            const orders = res?.data || [];

            if (!orders.length) {
                list.innerHTML = '<tr><td class="p-4 text-center text-muted" colspan="6">لا توجد طلبات في هذه الجلسة حتى الآن</td></tr>';
                return;
            }

            list.innerHTML = orders.map((order) => `
                <tr class="hover-bg transition">
                    <td class="p-2 border-b">${escapeHtml(order.order_number.split('-').pop())}</td>
                    <td class="p-2 border-b">${new Date(order.created_at).toLocaleTimeString('ar-EG')}</td>
                    <td class="p-2 border-b text-xs">${escapeHtml(order.type_label || order.type)}</td>
                    <td class="p-2 border-b"><span class="badge-status ${getStatusColor(order.status)}">${escapeHtml(order.status_label || order.status)}</span></td>
                    <td class="p-2 border-b"><span class="text-xs ${order.payment_status === 'paid' ? 'text-success' : 'text-warning'}">${escapeHtml(order.payment_status_label || order.payment_status)}</span></td>
                    <td class="p-2 border-b font-bold">${money(order.total)}</td>
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
            const summary = res.data;
            const grid = document.getElementById('session-stats-grid');
            const stats = [
                { label: 'إجمالي المبيعات', val: money(summary.cash_sales + summary.non_cash_sales), color: 'text-primary' },
                { label: 'مبيعات نقدية', val: money(summary.cash_sales), color: 'text-success' },
                { label: 'مبيعات أخرى (بطاقة)', val: money(summary.non_cash_sales), color: 'text-accent' },
                { label: 'الرصيد المتوقع بالدرج', val: money(summary.expected_cash), color: 'text-warning' },
                { label: 'إيداع نقدي', val: money(summary.cash_in), color: 'text-success' },
                { label: 'سحب نقدي', val: money(summary.cash_out), color: 'text-danger' },
                { label: 'إجمالي الطلبات', val: summary.order_count, color: 'text-primary' },
                { label: 'طلبات مدفوعة', val: summary.paid_orders_count, color: 'text-success' },
                { label: 'طلبات معلقة', val: summary.pending_orders_count, color: 'text-warning' },
            ];

            grid.innerHTML = stats.map((stat) => `
                <div class="bg-bg-primary p-3 rounded-lg border border-border">
                    <div class="text-xs text-muted mb-1">${stat.label}</div>
                    <div class="text-lg font-bold ${stat.color}">${stat.val}</div>
                </div>
            `).join('');
        } catch (err) {
            showToast('فشل تحميل الإحصائيات', 'error');
        } finally {
            document.getElementById('session-stats-loading').classList.add('hidden');
        }
    }

    function getStatusColor(status) {
        switch (status) {
            case 'confirmed': return 'badge-secondary';
            case 'preparing': return 'badge-warning';
            case 'ready':
            case 'delivered': return 'badge-success';
            case 'cancelled': return 'badge-danger';
            default: return 'badge-ghost';
        }
    }

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
        const amount = Number.parseFloat(document.getElementById('move-amount').value) || 0;
        const reason = document.getElementById('move-reason').value.trim();

        if (amount <= 0) {
            showToast('المبلغ غير صالح', 'error');
            return;
        }

        const btn = document.getElementById('move-btn');
        btn.disabled = true;
        btn.textContent = 'جاري المعالجة...';

        try {
            await api(`/drawers/${window.currentDrawerSessionId}/${activeMoveTab === 'in' ? 'cash-in' : 'cash-out'}`, {
                method: 'POST',
                body: { amount, notes: reason || null },
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

    function logout() {
        api('/auth/logout', { method: 'POST' }).catch(() => {});
        clearAuth();
        window.location.href = '/pos/login';
    }

    function goToCloseDrawer() {
        window.location.href = '/pos/close-drawer';
    }
</script>
@endsection
