@extends('layouts.app')
@section('title', 'فتح الدرج — Tarweaa POS')

@section('styles')
<style>
    .drawer-wrapper {
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .drawer-card {
        width: 100%;
        max-width: 460px;
    }
    .drawer-header {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    .drawer-header h2 { font-size: 1.5rem; font-weight: 700; }
    .drawer-header p { color: var(--text-secondary); margin-top: 0.25rem; }

    .shift-info {
        background: var(--bg-primary);
        border-radius: var(--radius);
        padding: 1rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .shift-icon {
        width: 42px; height: 42px;
        border-radius: var(--radius-sm);
        background: var(--success-bg);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
    }
    .shift-info-text span { display: block; }
    .shift-info-text .label { font-size: 0.75rem; color: var(--text-muted); }
    .shift-info-text .value { font-weight: 600; }

    .no-shift-msg {
        text-align: center;
        padding: 2rem;
    }
    .no-shift-msg .icon { font-size: 3rem; margin-bottom: 0.5rem; }
    .no-shift-msg p { color: var(--text-secondary); }

    .balance-input-wrapper {
        text-align: center;
        margin: 1.5rem 0 1rem;
    }
    .balance-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--accent);
        direction: ltr;
        min-height: 3.5rem;
    }
    .balance-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-bottom: 0.25rem;
    }

    .cashier-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        background: var(--bg-primary);
        border-radius: var(--radius);
        margin-bottom: 1rem;
    }
    .cashier-bar .name { font-weight: 600; }
    .cashier-bar .logout { color: var(--danger); cursor: pointer; font-size: 0.875rem; }
</style>
@endsection

@section('content')
<div class="drawer-wrapper">
    <div class="drawer-card">
        {{-- Loading --}}
        <div id="loading" class="text-center" style="padding:4rem 0">
            <div class="spinner" style="margin:0 auto 1rem"></div>
            <p class="text-muted">جاري التحقق...</p>
        </div>

        {{-- Main content (hidden until loaded) --}}
        <div id="main-content" class="hidden">

            <div class="drawer-header">
                <h2>🗄️ فتح الدرج</h2>
                <p>حدد الجهاز وأدخل الرصيد الافتتاحي</p>
            </div>

            <div class="card">
                <div class="card-body">
                    {{-- Cashier info --}}
                    <div class="cashier-bar">
                        <span class="name" id="cashier-name">—</span>
                        <span class="logout" onclick="logout()">تسجيل خروج</span>
                    </div>

                    {{-- Shift info --}}
                    <div id="shift-info"></div>

                    {{-- Device selector --}}
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">جهاز نقطة البيع</label>
                        <select id="device-select" class="form-input">
                            <option value="">جاري التحميل...</option>
                        </select>
                    </div>

                    {{-- Balance input --}}
                    <div class="balance-input-wrapper">
                        <div class="balance-label">الرصيد الافتتاحي</div>
                        <input type="text" id="balance-input" class="form-input form-input-lg" 
                               readonly onclick="openNumPad('balance-input', 'الرصيد الافتتاحي')" 
                               placeholder="0.00" style="background:transparent; border:none; color:var(--accent); font-size:3rem; font-weight:800; direction:ltr; cursor:pointer">
                    </div>

                    <button class="btn btn-primary btn-lg btn-block" id="open-btn" onclick="openDrawer()">
                        فتح الدرج
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    if (!requireAuth()) throw 'not-authed';
    if (!canAccessPosSurface()) {
        showToast('ليس لديك صلاحية للوصول إلى نقطة البيع', 'error');
        setTimeout(() => redirectToAuthorizedHome(), 800);
        throw new Error('POS access denied');
    }

    let balanceStr = '';
    let shiftData = null;
    let devices = [];

    (async function init() {
        const user = getUser();
        document.getElementById('cashier-name').textContent = user?.name || '—';

        try {
            // Check if already has active drawer → go to POS
            const drawerRes = await api('/drawers/active');
            if (drawerRes?.data) {
                window.location.href = '/pos';
                return;
            }

            // Check active shift
            const shiftRes = await api('/shifts/active');
            const shiftDiv = document.getElementById('shift-info');

            if (shiftRes?.data) {
                shiftData = shiftRes.data;
                shiftDiv.innerHTML = `
                    <div class="shift-info">
                        <div class="shift-icon">✅</div>
                        <div class="shift-info-text">
                            <span class="label">وردية مفتوحة</span>
                            <span class="value">${shiftData.shift_number || '#' + shiftData.id}</span>
                        </div>
                    </div>`;
            } else {
                shiftDiv.innerHTML = `
                    <div class="no-shift-msg">
                        <div class="icon">⚠️</div>
                        <p>لا توجد وردية مفتوحة.<br>يجب على المدير فتح وردية أولاً.</p>
                    </div>`;
                document.getElementById('open-btn').disabled = true;
            }

            // Load devices
            const devRes = await api('/pos/devices');
            const select = document.getElementById('device-select');
            select.innerHTML = '';
            if (devRes?.data?.length) {
                devices = devRes.data;
                devices.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name + (d.location ? ` — ${d.location}` : '');
                    select.appendChild(opt);
                });
            } else {
                select.innerHTML = '<option value="">لا توجد أجهزة</option>';
                document.getElementById('open-btn').disabled = true;
            }
        } catch (err) {
            showToast(err.message || 'خطأ في تحميل البيانات', 'error');
        }

        document.getElementById('loading').classList.add('hidden');
        document.getElementById('main-content').classList.remove('hidden');
    })();

    async function openDrawer() {
        const btn = document.getElementById('open-btn');
        const deviceId = document.getElementById('device-select').value;
        const balanceVal = document.getElementById('balance-input').value;

        if (!deviceId) { showToast('حدد جهاز نقطة البيع', 'error'); return; }
        if (!shiftData) { showToast('لا توجد وردية مفتوحة', 'error'); return; }

        btn.disabled = true;
        btn.textContent = 'جاري الفتح...';

        try {
            await api('/drawers/open', {
                method: 'POST',
                body: {
                    shift_id: shiftData.id,
                    pos_device_id: parseInt(deviceId),
                    opening_balance: parseFloat(balanceVal || '0'),
                },
            });
            showToast('تم فتح الدرج بنجاح');
            setTimeout(() => window.location.href = '/pos', 500);
        } catch (err) {
            showToast(err.message || 'فشل في فتح الدرج', 'error');
            btn.disabled = false;
            btn.textContent = 'فتح الدرج';
        }
    }

    function logout() {
        logoutPortal('/');
    }
</script>
@endsection
