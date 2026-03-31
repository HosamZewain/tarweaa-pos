<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Http\Requests\Auth\UpdatePortalPasswordRequest;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function __construct(
        private readonly PortalAccessService $portalAccessService,
    ) {}

    public function entry(Request $request): View|RedirectResponse
    {
        $requestedRedirect = $this->portalAccessService->sanitizeRedirectTarget(
            $request->query('redirect', $request->route('default_redirect')),
        );

        if ($request->user()) {
            return redirect()->to(
                $this->portalAccessService->resolveRedirectTarget($request->user(), $requestedRedirect),
            );
        }

        return view('portal.entry', [
            'requestedRedirect' => $requestedRedirect,
        ]);
    }

    public function launcher(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $entries = $this->portalAccessService->getLauncherEntries($user);

        abort_unless($entries !== [], 403);

        if (count($entries) === 1) {
            return redirect()->to($entries[0]['url']);
        }

        return view('portal.launcher', [
            'entries' => $entries,
            'user' => $user,
            'userSummary' => [
                'roles' => $user->roles()->pluck('display_name')->filter()->values(),
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'last_login_at' => optional($user->last_login_at?->timezone(config('app.business_timezone', 'Africa/Cairo')))->format('Y-m-d h:i A'),
            ],
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = $request->validated('username');

        $user = User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        return $this->completeLogin($request, $user);
    }

    public function pinLogin(PinLoginRequest $request): JsonResponse
    {
        $matchingUsers = User::query()
            ->where('pin', $request->validated('pin'))
            ->where('is_active', true)
            ->get();

        if ($matchingUsers->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => 'رمز PIN مستخدم لأكثر من حساب. يرجى استخدام كلمة المرور أو تعيين PIN مختلف لكل مستخدم.',
            ], 409);
        }

        $user = $matchingUsers->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'رمز PIN غير صحيح.',
            ], 401);
        }

        return $this->completeLogin($request, $user);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $this->portalAccessService->revokeFrontendBootstrapPayload($user, $request->session());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    public function updatePassword(UpdatePortalPasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!Hash::check($request->validated('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة.',
            ], 422);
        }

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث كلمة المرور بنجاح.',
        ]);
    }

    private function completeLogin(Request $request, User $user): JsonResponse
    {
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'الحساب غير نشط. يرجى التواصل مع المدير.',
            ], 403);
        }

        if (!$this->portalAccessService->hasAnyAccess($user)) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الحساب لا يملك أي واجهة تشغيل أو لوحة إدارة يمكن الوصول إليها.',
            ], 403);
        }

        $this->portalAccessService->revokeFrontendBootstrapPayload($request->user(), $request->session());

        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $user->markSignedIn();

        $payload = $this->portalAccessService->getFrontendBootstrapPayload($user, $request->session());

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $payload['user'],
                'token' => $payload['token'],
                'redirect_url' => $this->portalAccessService->resolveRedirectTarget(
                    $user,
                    $request->input('redirect'),
                ),
            ],
        ]);
    }
}
