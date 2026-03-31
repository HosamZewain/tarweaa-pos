@extends('layouts.app')
@section('title', 'الدخول الموحد — Tarweaa')

@section('styles')
    <style>
        .portal-wrapper {
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: linear-gradient(135deg, #0f1117 0%, #1a1030 50%, #0f1117 100%);
        }

        .portal-card {
            width: 100%;
            max-width: 460px;
        }

        .portal-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .portal-logo h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent), #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.25rem;
        }

        .portal-logo p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .portal-tabs {
            display: flex;
            background: var(--bg-primary);
            border-radius: var(--radius);
            padding: 4px;
            margin-bottom: 1.5rem;
        }

        .portal-tab {
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

        .portal-tab.active {
            background: var(--accent);
            color: #fff;
        }

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
    <div class="portal-wrapper">
        <div class="portal-card">
            <div class="portal-logo">
                <h1>Tarweaa</h1>
                <p>بوابة الدخول الموحدة</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="portal-tabs">
                        <button class="portal-tab active" onclick="switchTab('pin')">رمز PIN</button>
                        <button class="portal-tab" onclick="switchTab('password')">كلمة المرور</button>
                    </div>

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
        clearAuth();

        const REQUESTED_REDIRECT = @json($requestedRedirect);
        const PIN_MIN_LENGTH = 4;
        const PIN_MAX_LENGTH = 6;

        let currentTab = 'pin';
        let pinValue = '';

        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.portal-tab').forEach((t, i) => {
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

        async function portalLogin(endpoint, body, errorElementId, buttonId, defaultLabel) {
            const errEl = document.getElementById(errorElementId);
            const btn = document.getElementById(buttonId);

            errEl.textContent = '';
            btn.disabled = true;
            btn.textContent = 'جاري الدخول...';

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        ...body,
                        device_name: 'portal-web',
                        redirect: REQUESTED_REDIRECT,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw data;
                }

                setToken(data.data.token);
                setUser(data.data.user);
                window.location.href = data.data.redirect_url;
            } catch (error) {
                errEl.textContent = error.message || 'خطأ في تسجيل الدخول';
            } finally {
                btn.disabled = errorElementId === 'pin-error' ? pinValue.length < PIN_MIN_LENGTH : false;
                btn.textContent = defaultLabel;
            }
        }

        async function pinLogin() {
            if (pinValue.length < PIN_MIN_LENGTH) {
                document.getElementById('pin-error').textContent = 'رمز PIN يجب أن يكون 4 أرقام على الأقل';
                return;
            }

            await portalLogin('/portal/pin-login', { pin: pinValue }, 'pin-error', 'pin-submit', 'دخول');
        }

        async function passwordLogin(event) {
            event.preventDefault();

            await portalLogin('/portal/login', {
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
            }, 'pwd-error', 'pwd-submit', 'تسجيل الدخول');
        }
    </script>
@endsection
