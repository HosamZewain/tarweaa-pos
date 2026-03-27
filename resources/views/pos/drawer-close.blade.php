@extends('layouts.app')
@section('title', 'إغلاق الدرج — Tarweaa POS')

@section('styles')
<style>
    .close-wrapper {
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .close-card { width: 100%; max-width: 500px; }
    .close-header { text-align: center; margin-bottom: 1.5rem; }
    .close-header h2 { font-size: 1.5rem; font-weight: 700; }
    .close-header p { color: var(--text-secondary); margin-top: 0.25rem; }

    /* Summary rows */
    .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
    .summary-table td {
        padding: 0.65rem 0.5rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.9rem;
    }
    .summary-table td:first-child { color: var(--text-secondary); }
    .summary-table td:last-child { font-weight: 600; text-align: left; direction: ltr; }
    .summary-table tr.highlight td { font-size: 1rem; font-weight: 700; color: var(--accent); }

    /* Variance banner */
    .variance-banner {
        padding: 1rem;
        border-radius: var(--radius);
        text-align: center;
        margin: 1rem 0;
        font-weight: 700;
        font-size: 1.125rem;
    }
    .variance-banner.ok { background: var(--success-bg); color: var(--success); }
    .variance-banner.deficit { background: var(--danger-bg); color: var(--danger); }
    .variance-banner.surplus { background: var(--warning-bg); color: var(--warning); }
    .variance-label { font-size: 0.8rem; font-weight: 500; margin-bottom: 0.25rem; }

    /* Cash input */
    .actual-input {
        text-align: center;
        margin: 1rem 0;
    }
    .actual-value {
        font-size: 2.25rem;
        font-weight: 800;
        color: var(--text-primary);
        direction: ltr;
        min-height: 3rem;
    }
    .back-link {
        display: block;
        text-align: center;
        margin-top: 1rem;
        color: var(--text-secondary);
        font-size: 0.875rem;
        cursor: pointer;
    }
    .back-link:hover { color: var(--text-primary); }
</style>
@endsection

@section('content')
<div class="close-wrapper">
    <div class="close-card">
        {{-- Loading --}}
        <div id="loading" class="text-center" style="padding:4rem 0">
            <div class="spinner" style="margin:0 auto 1rem"></div>
            <p class="text-muted">جاري تحميل بيانات الدرج...</p>
        </div>

        {{-- Main --}}
        <div id="main-content" class="hidden">
            <div class="close-header">
                <h2>🔒 إغلاق الدرج</h2>
                <p>عدّ المبلغ النقدي في الدرج</p>
            </div>

            <div class="card">
                <div class="card-body">
                    {{-- Session Summary --}}
                    <table class="summary-table" id="summary-table">
                        <tr><td>رقم الجلسة</td><td id="s-number">—</td></tr>
                        <tr><td>الرصيد الافتتاحي</td><td id="s-opening">0.00</td></tr>
                        <tr><td>مبيعات نقدية</td><td id="s-sales">0.00</td></tr>
                        <tr><td>إضافات نقدية</td><td id="s-cashin">0.00</td></tr>
                        <tr><td>سحوبات نقدية</td><td id="s-cashout">0.00</td></tr>
                        <tr class="highlight"><td>المتوقع في الدرج</td><td id="s-expected">0.00</td></tr>
                    </table>

                    {{-- Actual cash input --}}
                    <div class="actual-input">
                        <div class="form-label" style="margin-bottom:0.5rem">المبلغ الفعلي</div>
                        <input type="text" id="actual-input" class="form-input form-input-lg" 
                               readonly onclick="openNumPad('actual-input', 'المبلغ الفعلي')" 
                               placeholder="0.00" style="background:transparent; border:none; color:var(--text-primary); font-size:3rem; font-weight:800; direction:ltr; cursor:pointer">
                    </div>

                    {{-- Variance display --}}
                    <div id="variance-banner" class="variance-banner ok hidden">
                        <div class="variance-label">الفرق</div>
                        <div id="variance-value">0.00 ج.م</div>
                    </div>

                    <button class="btn btn-danger btn-lg btn-block" id="close-btn" onclick="confirmClose()">
                        إغلاق الدرج
                    </button>

                    <a class="back-link" href="/pos">← العودة لنقطة البيع</a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Confirm modal --}}
<div id="confirm-modal" class="modal-overlay hidden" onclick="event.target===this && hideConfirm()">
    <div class="modal-content text-center">
        <div class="modal-title">⚠️ تأكيد إغلاق الدرج</div>
        <p style="margin-bottom:1rem; color:var(--text-secondary)">لن تتمكن من إجراء عمليات بعد الإغلاق</p>
        <div id="confirm-variance" style="margin-bottom:1rem"></div>
        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1" onclick="hideConfirm()">إلغاء</button>
            <button class="btn btn-danger flex-1" id="confirm-close-btn" onclick="doClose()">تأكيد الإغلاق</button>
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

    let actualStr = '';
    let sessionData = null;
    let expectedCash = 0;

    (async function init() {
        try {
            const res = await api('/drawers/active');
            if (!res?.data) {
                showToast('لا توجد جلسة درج مفتوحة', 'error');
                setTimeout(() => window.location.href = '/pos/drawer', 1000);
                return;
            }
            sessionData = res.data;

            // Get summary
            const sumRes = await api(`/drawers/${sessionData.id}/summary`);
            const s = sumRes?.data || {};

            document.getElementById('s-number').textContent = sessionData.session_number || '#' + sessionData.id;
            document.getElementById('s-opening').textContent = parseFloat(s.opening_balance || sessionData.opening_balance || 0).toFixed(2);
            document.getElementById('s-sales').textContent = parseFloat(s.cash_sales || 0).toFixed(2);
            document.getElementById('s-cashin').textContent = parseFloat(s.cash_in || 0).toFixed(2);
            document.getElementById('s-cashout').textContent = parseFloat(s.cash_out || 0).toFixed(2);

            expectedCash = parseFloat(s.expected_cash || s.expected_balance || 0);
            document.getElementById('s-expected').textContent = expectedCash.toFixed(2);

        } catch (err) {
            showToast(err.message || 'خطأ', 'error');
        }

        document.getElementById('loading').classList.add('hidden');
        document.getElementById('main-content').classList.remove('hidden');
    })();

    // Update variance when input changes
    document.getElementById('actual-input').addEventListener('input', updateVariance);

    function updateVariance() {
        const actualStr = document.getElementById('actual-input').value;
        const actual = parseFloat(actualStr || '0');
        const diff = actual - expectedCash;
        const banner = document.getElementById('variance-banner');
        const valueEl = document.getElementById('variance-value');

        if (!actualStr) {
            banner.classList.add('hidden');
            return;
        }

        banner.classList.remove('hidden');

        if (Math.abs(diff) < 0.01) {
            banner.className = 'variance-banner ok';
            valueEl.textContent = '✅ مطابق';
        } else if (diff > 0) {
            banner.className = 'variance-banner surplus';
            valueEl.textContent = `فائض: +${diff.toFixed(2)} ج.م`;
        } else {
            banner.className = 'variance-banner deficit';
            valueEl.textContent = `عجز: ${diff.toFixed(2)} ج.م`;
        }
    }

    function confirmClose() {
        const actualStr = document.getElementById('actual-input').value;
        const actual = parseFloat(actualStr || '0');
        if (!actualStr) { showToast('أدخل المبلغ الفعلي', 'error'); return; }

        const diff = actual - expectedCash;
        const confirmVar = document.getElementById('confirm-variance');

        if (Math.abs(diff) < 0.01) {
            confirmVar.innerHTML = '<div class="badge badge-success" style="font-size:0.9rem;padding:0.5rem 1rem">✅ مطابق — بدون فرق</div>';
        } else if (diff > 0) {
            confirmVar.innerHTML = `<div class="badge badge-warning" style="font-size:0.9rem;padding:0.5rem 1rem">فائض: +${diff.toFixed(2)} ج.م</div>`;
        } else {
            confirmVar.innerHTML = `<div class="badge badge-danger" style="font-size:0.9rem;padding:0.5rem 1rem">عجز: ${diff.toFixed(2)} ج.م</div>`;
        }

        document.getElementById('confirm-modal').classList.remove('hidden');
    }

    function hideConfirm() {
        document.getElementById('confirm-modal').classList.add('hidden');
    }

    async function doClose() {
        const actualStr = document.getElementById('actual-input').value;
        const btn = document.getElementById('confirm-close-btn');
        btn.disabled = true;
        btn.textContent = 'جاري الإغلاق...';

        try {
            await api(`/drawers/${sessionData.id}/close`, {
                method: 'POST',
                body: {
                    actual_cash: parseFloat(actualStr || '0'),
                },
            });
            showToast('تم إغلاق الدرج بنجاح');
            setTimeout(() => {
                clearAuth();
                window.location.href = '/pos/login';
            }, 1000);
        } catch (err) {
            showToast(err.message || 'فشل في إغلاق الدرج', 'error');
            btn.disabled = false;
            btn.textContent = 'تأكيد الإغلاق';
        }
    }
</script>
@endsection
