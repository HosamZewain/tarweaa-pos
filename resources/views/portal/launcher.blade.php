@extends('layouts.app')
@section('title', 'بوابة التشغيل — Tarweaa')

@section('styles')
    <style>
        html,
        body {
            overflow-y: auto;
            overflow-x: hidden;
        }

        .launcher-shell {
            min-height: 100dvh;
            padding: 2rem;
            overflow: visible;
            background:
                radial-gradient(circle at top right, rgba(99, 102, 241, 0.18), transparent 28%),
                radial-gradient(circle at bottom left, rgba(34, 197, 94, 0.12), transparent 22%),
                var(--bg-primary);
        }

        .launcher-wrap {
            max-width: 1120px;
            margin: 0 auto;
        }

        .launcher-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.4rem 1.6rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            background:
                linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(15, 17, 23, 0.4)),
                rgba(24, 27, 38, 0.94);
            box-shadow: 0 18px 60px rgba(0, 0, 0, 0.28);
        }

        .launcher-layout {
            display: grid;
            grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
            gap: 1rem;
        }

        .launcher-side {
            display: grid;
            gap: 1rem;
            align-content: start;
        }

        .launcher-actions {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .launcher-title h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.4rem;
            letter-spacing: -0.03em;
        }

        .launcher-title p {
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .launcher-hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 1rem;
        }

        .launcher-hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 0.8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-secondary);
            font-size: 0.84rem;
            font-weight: 700;
        }

        .launcher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem;
        }

        .launcher-panel {
            padding: 1.25rem 1.3rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 22px;
            background: rgba(25, 29, 39, 0.94);
            box-shadow: 0 12px 34px rgba(0, 0, 0, 0.2);
        }

        .launcher-panel h2 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.85rem;
        }

        .profile-value {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .profile-sub {
            color: var(--text-secondary);
            margin-bottom: 1.1rem;
            font-size: 0.92rem;
        }

        .profile-list {
            display: grid;
            gap: 0.75rem;
        }

        .profile-row {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .profile-row label {
            color: var(--text-muted);
            font-size: 0.82rem;
        }

        .profile-row span {
            font-weight: 600;
        }

        .badge-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .quick-actions {
            display: grid;
            gap: 0.75rem;
        }

        .account-avatar {
            width: 58px;
            height: 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(34, 197, 94, 0.2));
            color: #fff;
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 0.95rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .quick-action-button {
            justify-content: space-between;
            padding: 0.95rem 1rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-primary);
            font-weight: 700;
        }

        .quick-action-button:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        @media (max-width: 900px) {
            .launcher-header {
                grid-template-columns: 1fr;
            }

            .launcher-layout {
                grid-template-columns: 1fr;
            }
        }

        .launcher-card {
            display: block;
            position: relative;
            overflow: hidden;
            padding: 1.3rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(36, 40, 54, 0.96), rgba(25, 29, 39, 0.96));
            transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
            box-shadow: 0 12px 34px rgba(0, 0, 0, 0.18);
        }

        .launcher-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent);
            background: linear-gradient(180deg, rgba(45, 50, 69, 0.98), rgba(28, 33, 45, 0.98));
            box-shadow: 0 18px 44px rgba(0, 0, 0, 0.24);
        }

        .launcher-card::after {
            content: '';
            position: absolute;
            inset-inline-end: -22px;
            top: -22px;
            width: 88px;
            height: 88px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
        }

        .launcher-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .launcher-card-icon {
            width: 54px;
            height: 54px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            font-size: 0.88rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .launcher-card-icon.accent { background: linear-gradient(135deg, #6366f1, #4f46e5); }
        .launcher-card-icon.success { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .launcher-card-icon.info { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .launcher-card-icon.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .launcher-card-icon.danger { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .launcher-card-group {
            display: inline-flex;
            padding: 0.35rem 0.6rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-secondary);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .launcher-card-title {
            font-size: 1.18rem;
            font-weight: 800;
            margin-bottom: 0.45rem;
        }

        .launcher-card p {
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 3.2rem;
        }

        .launcher-card .meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1rem;
            color: var(--accent);
            font-weight: 700;
            font-size: 0.9rem;
            padding-top: 0.9rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
    </style>
@endsection

@section('content')
    <div class="launcher-shell">
        <div class="launcher-wrap">
            <div class="launcher-header">
                <div class="launcher-title">
                    <h1>بوابة التشغيل</h1>
                    <p>{{ $user->name }} — اختر الواجهة المسموح لك بالوصول إليها.</p>
                    <div class="launcher-hero-meta">
                        <span class="launcher-hero-pill">عدد الواجهات: {{ count($entries) }}</span>
                        <span class="launcher-hero-pill">الحساب: {{ $userSummary['username'] ?: '—' }}</span>
                    </div>
                </div>
                <div class="launcher-actions">
                    <button type="button" class="btn btn-secondary" onclick="openPasswordModal()">تغيير كلمة المرور</button>
                    <button type="button" class="btn btn-secondary" onclick="logoutPortal('/')">تسجيل الخروج</button>
                </div>
            </div>

            <div class="launcher-layout">
                <div class="launcher-side">
                    <div class="launcher-panel">
                        <div class="account-avatar">{{ mb_substr($user->name, 0, 1) }}</div>
                        <h2>بيانات الحساب</h2>
                        <div class="profile-value">{{ $user->name }}</div>
                        <div class="profile-sub">{{ $userSummary['username'] ?: '—' }}</div>

                        <div class="profile-list">
                            <div class="profile-row">
                                <label>البريد الإلكتروني</label>
                                <span>{{ $userSummary['email'] ?: '—' }}</span>
                            </div>
                            <div class="profile-row">
                                <label>الهاتف</label>
                                <span>{{ $userSummary['phone'] ?: '—' }}</span>
                            </div>
                            <div class="profile-row">
                                <label>آخر تسجيل دخول</label>
                                <span>{{ $userSummary['last_login_at'] ?: '—' }}</span>
                            </div>
                            <div class="profile-row">
                                <label>الأدوار</label>
                                <div class="badge-wrap">
                                    @forelse ($userSummary['roles'] as $role)
                                        <span class="badge badge-secondary">{{ $role }}</span>
                                    @empty
                                        <span>—</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="launcher-panel">
                        <h2>إجراءات سريعة</h2>
                        <div class="quick-actions">
                            <button type="button" class="btn quick-action-button btn-block" onclick="openPasswordModal()">تغيير كلمة المرور <span>→</span></button>
                            <button type="button" class="btn quick-action-button btn-block" onclick="window.location.href='{{ route('portal.entry') }}'">تحديث البوابة <span>↻</span></button>
                        </div>
                    </div>
                </div>

                <div class="launcher-grid">
                    @foreach ($entries as $entry)
                        <a href="{{ $entry['url'] }}" class="launcher-card">
                            <div class="launcher-card-head">
                                <span class="launcher-card-icon {{ $entry['tone'] }}">{{ $entry['icon'] }}</span>
                                <span class="launcher-card-group">{{ $entry['group'] }}</span>
                            </div>
                            <div class="launcher-card-title">{{ $entry['title'] }}</div>
                            <p>{{ $entry['description'] }}</p>
                            <div class="meta">
                                <span>فتح الواجهة</span>
                                <span>→</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div id="password-modal" class="modal-overlay hidden" onclick="event.target === this && closePasswordModal()">
        <div class="modal-content">
            <div class="modal-title">تغيير كلمة المرور</div>
            <form onsubmit="submitPasswordChange(event)" class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label" for="current-password">كلمة المرور الحالية</label>
                    <input id="current-password" type="password" class="form-input" autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="new-password">كلمة المرور الجديدة</label>
                    <input id="new-password" type="password" class="form-input" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="new-password-confirmation">تأكيد كلمة المرور الجديدة</label>
                    <input id="new-password-confirmation" type="password" class="form-input" autocomplete="new-password">
                </div>
                <div id="password-change-error" class="login-error" style="text-align:right; min-height:auto;"></div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secondary flex-1" onclick="closePasswordModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary flex-1" id="password-change-submit">حفظ</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function openPasswordModal() {
            document.getElementById('password-modal').classList.remove('hidden');
            document.getElementById('password-change-error').textContent = '';
            document.getElementById('current-password').focus();
        }

        function closePasswordModal() {
            document.getElementById('password-modal').classList.add('hidden');
            document.getElementById('current-password').value = '';
            document.getElementById('new-password').value = '';
            document.getElementById('new-password-confirmation').value = '';
            document.getElementById('password-change-error').textContent = '';
        }

        async function submitPasswordChange(event) {
            event.preventDefault();

            const submitButton = document.getElementById('password-change-submit');
            const errorElement = document.getElementById('password-change-error');

            submitButton.disabled = true;
            submitButton.textContent = 'جاري الحفظ...';
            errorElement.textContent = '';

            try {
                const response = await fetch(@json(route('portal.password')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        current_password: document.getElementById('current-password').value,
                        password: document.getElementById('new-password').value,
                        password_confirmation: document.getElementById('new-password-confirmation').value,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw data;
                }

                showToast(data.message || 'تم تحديث كلمة المرور بنجاح');
                closePasswordModal();
            } catch (error) {
                errorElement.textContent = error.message || 'تعذر تحديث كلمة المرور';
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'حفظ';
            }
        }
    </script>
@endsection
