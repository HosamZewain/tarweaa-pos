<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نظام نقطة البيع — Tarweaa')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        /* ═══════════════════════════════════════
           RESET & BASE
           ═══════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f1117;
            color: #e4e6eb;
            direction: rtl;
            min-height: 100dvh;
            overflow: hidden;
            -webkit-user-select: none;
            user-select: none;
        }
        input, select, textarea, button { font-family: inherit; }
        a { color: inherit; text-decoration: none; }

        /* ═══════════════════════════════════════
           CSS VARIABLES
           ═══════════════════════════════════════ */
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #1a1d27;
            --bg-card: #242836;
            --bg-card-hover: #2d3245;
            --bg-input: #1a1d27;
            --border: #2d3245;
            --border-focus: #6366f1;
            --text-primary: #e4e6eb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --accent: #6366f1;
            --accent-hover: #818cf8;
            --accent-glow: rgba(99, 102, 241, 0.3);
            --success: #22c55e;
            --success-bg: rgba(34, 197, 94, 0.15);
            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.15);
            --warning: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.15);
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
            --shadow: 0 4px 20px rgba(0
            , 0, 0, 0.3);
        }

        /* ═══════════════════════════════════════
           UTILITY CLASSES
           ═══════════════════════════════════════ */
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .gap-1 { gap: 0.25rem; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }
        .w-full { width: 100%; }
        .h-full { height: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .text-lg { font-size: 1.125rem; }
        .text-xl { font-size: 1.25rem; }
        .text-2xl { font-size: 1.5rem; }
        .text-3xl { font-size: 1.875rem; }
        .font-bold { font-weight: 700; }
        .font-medium { font-weight: 500; }
        .text-muted { color: var(--text-secondary); }
        .text-secondary { color: var(--text-secondary); }
        .text-accent { color: var(--accent); }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .text-warning { color: var(--warning); }
        .hidden { display: none !important; }
        .overflow-auto { overflow: auto; }
        .overflow-x-auto { overflow-x: auto; }
        .flex-1 { flex: 1; }
        .shrink-0 { flex-shrink: 0; }
        .grid { display: grid; }
        .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cursor-pointer { cursor: pointer; }
        .transition { transition: all 0.15s ease; }
        .rounded-lg { border-radius: var(--radius); }
        .border-b { border-bottom: 1px solid var(--border); }
        .border-t { border-top: 1px solid var(--border); }
        .border-border { border-color: var(--border); }
        .p-2 { padding: 0.5rem; }
        .p-3 { padding: 0.75rem; }
        .p-4 { padding: 1rem; }
        .mb-1 { margin-bottom: 0.25rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mt-1 { margin-top: 0.25rem; }
        .mt-4 { margin-top: 1rem; }
        .pb-2 { padding-bottom: 0.5rem; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .hover-bg:hover { background: var(--bg-card-hover); }
        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .max-w-\[100px\] { max-width: 100px; }
        .bg-bg-primary { background: var(--bg-primary); }
        .bg-bg-secondary { background: var(--bg-secondary); }

        @media (min-width: 768px) {
            .md\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        /* ═══════════════════════════════════════
           BUTTON SYSTEM
           ═══════════════════════════════════════ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            min-height: 48px;
            min-width: 48px;
            white-space: nowrap;
        }
        .btn:active { transform: scale(0.97); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        .btn-primary:hover:not(:disabled) { background: var(--accent-hover); }
        .btn-primary:active { box-shadow: 0 0 20px var(--accent-glow); }

        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover:not(:disabled) { background: var(--bg-card-hover); }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        .btn-danger:hover:not(:disabled) { background: #dc2626; }

        .btn-success {
            background: var(--success);
            color: #fff;
        }
        .btn-success:hover:not(:disabled) { background: #16a34a; }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
        }
        .btn-ghost:hover:not(:disabled) { background: var(--bg-card); color: var(--text-primary); }

        .btn-lg { padding: 1rem 2rem; font-size: 1.125rem; min-height: 56px; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; min-height: 40px; }
        .btn-icon { padding: 0.75rem; }
        .btn-block { width: 100%; }

        /* ═══════════════════════════════════════
           FORM INPUTS
           ═══════════════════════════════════════ */
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-input);
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-size: 1rem;
            min-height: 48px;
            transition: border-color 0.15s;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        .form-input::placeholder { color: var(--text-muted); }
        .form-input-lg { font-size: 1.5rem; padding: 1rem 1.25rem; min-height: 60px; text-align: center; }
        select.form-input { appearance: none; cursor: pointer; }

        /* ═══════════════════════════════════════
           CARD
           ═══════════════════════════════════════ */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }
        .card-body { padding: 1.25rem; }

        /* ═══════════════════════════════════════
           NUMPAD
           ═══════════════════════════════════════ */
        .numpad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            max-width: 320px;
            margin: 0 auto;
        }
        .numpad-key {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 64px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.1s;
        }
        .numpad-key:active { background: var(--accent); color: #fff; transform: scale(0.95); }
        .numpad-key.numpad-clear { background: var(--danger-bg); color: var(--danger); font-size: 1rem; }
        .numpad-key.numpad-back { font-size: 1.25rem; }
        .numpad-key.numpad-enter { background: var(--accent); color: #fff; }
        .numpad-key.numpad-wide { grid-column: span 2; }
        .numpad-key.numpad-dot { font-size: 2rem; }

        /* ═══════════════════════════════════════
           BADGE
           ═══════════════════════════════════════ */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-secondary { background: rgba(99, 102, 241, 0.16); color: var(--accent); }
        .badge-ghost { background: rgba(156, 163, 175, 0.12); color: var(--text-secondary); }

        /* ═══════════════════════════════════════
           MODAL
           ═══════════════════════════════════════ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            width: 90%;
            max-width: 480px;
            max-height: 90dvh;
            overflow-y: auto;
            box-shadow: var(--shadow);
        }
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        /* ═══════════════════════════════════════
           TOAST
           ═══════════════════════════════════════ */
        .toast-container {
            position: fixed;
            top: 1rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 200;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .toast {
            padding: 0.875rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 500;
            animation: toast-in 0.3s ease;
            min-width: 280px;
            text-align: center;
        }
        .toast-success { background: var(--success); color: #fff; }
        .toast-error { background: var(--danger); color: #fff; }
        @keyframes toast-in { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* ═══════════════════════════════════════
           LOADING
           ═══════════════════════════════════════ */
        .spinner {
            width: 32px; height: 32px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-screen {
            position: fixed; inset: 0;
            background: var(--bg-primary);
            display: flex; align-items: center; justify-content: center;
            z-index: 300;
        }

        /* ═══════════════════════════════════════
           SCROLLBAR
           ═══════════════════════════════════════ */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    </style>
    @yield('styles')
</head>
<body>
    <div id="toast-container" class="toast-container"></div>

    {{-- ═══ Global Numeric Keypad Modal ═══ --}}
    <div id="numpad-modal" class="modal-overlay hidden" style="z-index:9999">
        <div class="modal-content" style="max-width:320px; padding:1rem;">
            <div class="modal-title" id="numpad-title" style="margin-bottom:0.5rem">لوحة الأرقام</div>
            <div id="numpad-display" style="background:var(--bg-secondary); padding:1rem; border-radius:var(--radius); font-size:1.5rem; text-align:center; margin-bottom:1rem; font-weight:bold; min-height:3.5rem; display:flex; align-items:center; justify-content:center; direction:ltr">0</div>
            
            <style>
                .numpad-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
                .num-btn { 
                    padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius);
                    background: var(--bg-card); font-size: 1.25rem; font-weight: bold; cursor: pointer;
                    transition: all 0.1s; display: flex; align-items: center; justify-content: center;
                    color: var(--text-primary);
                }
                .num-btn:active { background: var(--accent); color: #fff; transform: scale(0.95); }
                .num-btn.action { background: var(--bg-secondary); color: var(--text-secondary); }
                .num-btn.confirm { background: var(--success); color: #fff; grid-column: span 2; }
            </style>

            <div class="numpad-grid">
                <button class="num-btn" onclick="npInput('1')">1</button>
                <button class="num-btn" onclick="npInput('2')">2</button>
                <button class="num-btn" onclick="npInput('3')">3</button>
                <button class="num-btn" onclick="npInput('4')">4</button>
                <button class="num-btn" onclick="npInput('5')">5</button>
                <button class="num-btn" onclick="npInput('6')">6</button>
                <button class="num-btn" onclick="npInput('7')">7</button>
                <button class="num-btn" onclick="npInput('8')">8</button>
                <button class="num-btn" onclick="npInput('9')">9</button>
                <button class="num-btn" onclick="npInput('.')">.</button>
                <button class="num-btn" onclick="npInput('0')">0</button>
                <button class="num-btn action" onclick="npBackspace()">⌫</button>
                <button class="num-btn action" onclick="npClear()">C</button>
                <button class="num-btn confirm" onclick="npConfirm()">تأكيد</button>
            </div>
        </div>
    </div>

    @yield('content')

    <script>
        /* ═══════════════════════════════════════
           GLOBAL JS UTILITIES
           ═══════════════════════════════════════ */

        // Token management
        const TOKEN_KEY = 'pos_token';
        const USER_KEY  = 'pos_user';

        function getToken() { return localStorage.getItem(TOKEN_KEY); }
        function setToken(token) { localStorage.setItem(TOKEN_KEY, token); }
        function getUser() { try { return JSON.parse(localStorage.getItem(USER_KEY)); } catch { return null; } }
        function setUser(user) { localStorage.setItem(USER_KEY, JSON.stringify(user)); }
        function clearAuth() { localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(USER_KEY); }
        function isLoggedIn() { return !!getToken(); }
        function hasRole(roleName, user = getUser()) {
            return Array.isArray(user?.roles) && user.roles.some(role => role.name === roleName);
        }
        function hasPermission(permissionName, user = getUser()) {
            if (hasRole('admin', user)) return true;

            return Array.isArray(user?.permissions) && user.permissions.includes(permissionName);
        }
        function canAccessPosSurface(user = getUser()) {
            return Boolean(
                user?.can_access_pos ||
                hasRole('admin', user) ||
                hasRole('manager', user) ||
                hasRole('cashier', user)
            );
        }
        function canAccessKitchenSurface(user = getUser()) {
            return Boolean(user?.can_access_kitchen || hasPermission('view_kitchen', user));
        }
        function canAccessCounterSurface(user = getUser()) {
            return Boolean(user?.can_access_counter || hasPermission('view_counter_screen', user));
        }
        function getCurrentPathWithQuery() {
            return `${window.location.pathname}${window.location.search}`;
        }
        function getSafeRedirectTarget(candidate, fallback = '/pos/drawer') {
            if (!candidate || typeof candidate !== 'string') return fallback;
            if (!candidate.startsWith('/')) return fallback;
            if (candidate.startsWith('//')) return fallback;

            return candidate;
        }
        function getAuthorizedHome(user = getUser()) {
            if (canAccessPosSurface(user)) {
                return '/pos/drawer';
            }

            if (canAccessKitchenSurface(user)) {
                return '/kitchen';
            }

            if (canAccessCounterSurface(user)) {
                return '/counter-screen/odd';
            }

            return '/pos/login';
        }
        function redirectToAuthorizedHome(user = getUser()) {
            window.location.href = getAuthorizedHome(user);
        }
        function redirectToLogin(redirectTo = getCurrentPathWithQuery()) {
            const target = getSafeRedirectTarget(redirectTo, '/pos/drawer');
            window.location.href = `/pos/login?redirect=${encodeURIComponent(target)}`;
        }

        // API helper
        async function api(url, options = {}) {
            const token = getToken();
            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...options.headers,
            };

            if (token) headers['Authorization'] = `Bearer ${token}`;
            if (options.body && !(options.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }

            try {
                const res = await fetch(`/api${url}`, { ...options, headers });

                if (res.status === 401) {
                    clearAuth();
                    redirectToLogin();
                    return null;
                }

                const data = await res.json();
                if (!res.ok) throw { status: res.status, ...data };
                return data;
            } catch (err) {
                if (err.message && !err.success) throw err;
                throw { success: false, message: 'خطأ في الاتصال بالسيرفر' };
            }
        }

        // Toast notifications
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
        }

        // Format money
        function money(amount) {
            return parseFloat(amount || 0).toFixed(2) + ' ج.م';
        }

        // Guard: redirect if not logged in
        function requireAuth(redirectTo = getCurrentPathWithQuery()) {
            if (!isLoggedIn()) {
                redirectToLogin(redirectTo);
                return false;
            }
            return true;
        }

        /* ═══════════════════════════════════════
           GLOBAL NUMERIC KEYPAD LOGIC
           ═══════════════════════════════════════ */
        let npTargetId = null;
        let npCurrentVal = '';

        function openNumPad(targetId, title = 'لوحة الأرقام') {
            npTargetId = targetId;
            const targetEl = document.getElementById(targetId);
            npCurrentVal = targetEl.value || '';
            document.getElementById('numpad-title').textContent = title;
            updateNpDisplay();
            document.getElementById('numpad-modal').classList.remove('hidden');
        }

        function updateNpDisplay() {
            document.getElementById('numpad-display').textContent = npCurrentVal || '0';
        }

        function npInput(val) {
            if (val === '.' && npCurrentVal.includes('.')) return;
            if (npCurrentVal === '0' && val !== '.') npCurrentVal = '';
            npCurrentVal += val;
            updateNpDisplay();
        }

        function npBackspace() {
            npCurrentVal = npCurrentVal.slice(0, -1);
            updateNpDisplay();
        }

        function npClear() {
            npCurrentVal = '';
            updateNpDisplay();
        }

        function npConfirm() {
            const el = document.getElementById(npTargetId);
            el.value = npCurrentVal;
            // Trigger input event for reactive calculation
            el.dispatchEvent(new Event('input'));
            document.getElementById('numpad-modal').classList.add('hidden');
        }
    </script>
    @yield('scripts')
</body>
</html>
