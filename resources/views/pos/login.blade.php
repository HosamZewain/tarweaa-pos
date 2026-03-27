@extends('layouts.app')
@section('title', 'تسجيل الدخول — Tarweaa POS')

@section('styles')
    <style>
        .login-wrapper {
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: linear-gradient(135deg, #0f1117 0%, #1a1030 50%, #0f1117 100%);
        }

        .login-card {
            width: 100%;
            max-width: 420px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.25rem;
        }

        .login-logo p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Tabs */
        .login-tabs {
            display: flex;
            background: var(--bg-primary);
            border-radius: var(--radius);
            padding: 4px;
            margin-bottom: 1.5rem;
        }

        .login-tab {
            flex: 1;
            padding: 0.75rem;
            text-align: center;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            color: var(--text-secondary);
            font-size: 0.95rem;
            border: none;
            background: none;
        }

        .login-tab.active {
            background: var(--accent);
            color: #fff;
        }

        /* PIN display */
        .pin-display {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .pin-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid var(--border);
            transition: all 0.15s;
        }

        .pin-dot.filled {
            background: var(--accent);
            border-color: var(--accent);
            box-shadow: 0 0 10px var(--accent-glow);
        }

        .login-error {
            color: var(--danger);
            font-size: 0.875rem;
            text-align: center;
            min-height: 1.25rem;
            margin-top: 0.5rem;
        }

        .pin-help {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
@endsection

@section('content')
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <h1>Tarweaa POS</h1>
                <p>نظام نقطة البيع</p>
            </div>

            <div class="card">
                <div class="card-body">
                    {{-- Tabs --}}
                    <div class="login-tabs">
                        <button class="login-tab active" onclick="switchTab('pin')">رمز PIN</button>
                        <button class="login-tab" onclick="switchTab('password')">كلمة المرور</button>
                    </div>

                    {{-- PIN Login --}}
                    <div id="tab-pin">
                        <div class="pin-display" id="pin-dots">
                            <div class="pin-dot"></div>
                            <div class="pin-dot"></div>
                            <div class="pin-dot"></div>
                            <div class="pin-dot"></div>
                            <div class="pin-dot"></div>
                            <div class="pin-dot"></div>
                        </div>

                        <div class="pin-help">أدخل رمز PIN من 4 إلى 6 أرقام ثم اضغط دخول</div>

                        <div class="numpad">
                            <button class="numpad-key" onclick="pinAdd('1')">١</button>
                            <button class="numpad-key" onclick="pinAdd('2')">٢</button>
                            <button class="numpad-key" onclick="pinAdd('3')">٣</button>
                            <button class="numpad-key" onclick="pinAdd('4')">٤</button>
                            <button class="numpad-key" onclick="pinAdd('5')">٥</button>
                            <button class="numpad-key" onclick="pinAdd('6')">٦</button>
                            <button class="numpad-key" onclick="pinAdd('7')">٧</button>
                            <button class="numpad-key" onclick="pinAdd('8')">٨</button>
                            <button class="numpad-key" onclick="pinAdd('9')">٩</button>
                            <button class="numpad-key numpad-clear" onclick="pinClear()">مسح</button>
                            <button class="numpad-key" onclick="pinAdd('0')">٠</button>
                            <button class="numpad-key numpad-back" onclick="pinBack()">⌫</button>
                        </div>

                        <button class="btn btn-primary btn-lg btn-block" id="pin-submit" onclick="pinLogin()" style="margin-top: 1rem;"
                            disabled>
                            دخول
                        </button>

                        <div class="login-error" id="pin-error"></div>
                    </div>

                    {{-- Password Login --}}
                    <div id="tab-password" class="hidden">
                        <form onsubmit="passwordLogin(event)" class="flex flex-col gap-4">
                            <div class="form-group">
                                <label class="form-label">اسم المستخدم أو البريد الإلكتروني</label>
                                <input type="text" id="username" class="form-input" placeholder="أدخل اسم المستخدم أو البريد الإلكتروني"
                                    autocomplete="username">
                            </div>
                            <div class="form-group">
                                <label class="form-label">كلمة المرور</label>
                                <input type="password" id="password" class="form-input" placeholder="أدخل كلمة المرور"
                                    autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg btn-block" id="pwd-submit">
                                تسجيل الدخول
                            </button>
                            <div class="login-error" id="pwd-error"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Clear any stale auth on login page load
        clearAuth();

        let currentTab = 'pin';
        let pinValue = '';
        const PIN_MIN_LENGTH = 4;
        const PIN_MAX_LENGTH = 6;

        function getPostLoginRedirect(user) {
            const params = new URLSearchParams(window.location.search);
            const target = getSafeRedirectTarget(params.get('redirect'), getAuthorizedHome(user));

            if (target.startsWith('/kitchen')) {
                return canAccessKitchenSurface(user) ? target : getAuthorizedHome(user);
            }

            if (target.startsWith('/pos')) {
                return canAccessPosSurface(user) ? target : getAuthorizedHome(user);
            }

            return getAuthorizedHome(user);
        }

        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.login-tab').forEach((t, i) => {
                t.classList.toggle('active', (tab === 'pin' && i === 0) || (tab === 'password' && i === 1));
            });
            document.getElementById('tab-pin').classList.toggle('hidden', tab !== 'pin');
            document.getElementById('tab-password').classList.toggle('hidden', tab !== 'password');
            pinClear();
            document.getElementById('pin-error').textContent = '';
            document.getElementById('pwd-error').textContent = '';
        }

        function updateDots() {
            document.querySelectorAll('#pin-dots .pin-dot').forEach((dot, i) => {
                dot.classList.toggle('filled', i < pinValue.length);
            });

            const submit = document.getElementById('pin-submit');
            if (submit) {
                submit.disabled = pinValue.length < PIN_MIN_LENGTH;
            }
        }

        function pinAdd(digit) {
            if (pinValue.length >= PIN_MAX_LENGTH) return;
            pinValue += digit;
            updateDots();
        }

        function pinBack() {
            pinValue = pinValue.slice(0, -1);
            updateDots();
            document.getElementById('pin-error').textContent = '';
        }

        function pinClear() {
            pinValue = '';
            updateDots();
            document.getElementById('pin-error').textContent = '';
        }

        async function pinLogin() {
            const errEl = document.getElementById('pin-error');
            const btn = document.getElementById('pin-submit');
            errEl.textContent = '';

            if (pinValue.length < PIN_MIN_LENGTH) {
                errEl.textContent = 'رمز PIN يجب أن يكون 4 أرقام على الأقل';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'جاري الدخول...';

            try {
                const data = await api('/auth/pin-login', {
                    method: 'POST',
                    body: { pin: pinValue, device_name: 'pos-terminal' },
                });
                setToken(data.data.token);
                setUser(data.data.user);
                window.location.href = getPostLoginRedirect(data.data.user);
            } catch (err) {
                errEl.textContent = err.message || 'خطأ في تسجيل الدخول';
                pinClear();
            } finally {
                btn.disabled = pinValue.length < PIN_MIN_LENGTH;
                btn.textContent = 'دخول';
            }
        }

        async function passwordLogin(e) {
            e.preventDefault();
            const errEl = document.getElementById('pwd-error');
            const btn = document.getElementById('pwd-submit');
            errEl.textContent = '';
            btn.disabled = true;
            btn.textContent = 'جاري الدخول...';

            try {
                const data = await api('/auth/login', {
                    method: 'POST',
                    body: {
                        username: document.getElementById('username').value,
                        password: document.getElementById('password').value,
                        device_name: 'pos-terminal',
                    },
                });
                setToken(data.data.token);
                setUser(data.data.user);
                window.location.href = getPostLoginRedirect(data.data.user);
            } catch (err) {
                errEl.textContent = err.message || 'خطأ في تسجيل الدخول';
            } finally {
                btn.disabled = false;
                btn.textContent = 'تسجيل الدخول';
            }
        }
    </script>
@endsection
