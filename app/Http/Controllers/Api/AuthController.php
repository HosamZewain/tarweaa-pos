<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login with username + password → Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('بيانات الدخول غير صحيحة.', 401);
        }

        if (!$user->is_active) {
            return $this->error('الحساب غير نشط. يرجى التواصل مع المدير.', 403);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return $this->success([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ], 'تم تسجيل الدخول بنجاح');
    }

    /**
     * Quick POS login with PIN code.
     */
    public function pinLogin(PinLoginRequest $request): JsonResponse
    {
        $user = User::where('pin', $request->pin)->first();

        if (!$user) {
            return $this->error('رمز PIN غير صحيح.', 401);
        }

        if (!$user->is_active) {
            return $this->error('الحساب غير نشط. يرجى التواصل مع المدير.', 403);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return $this->success([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ], 'تم تسجيل الدخول بنجاح');
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'تم تسجيل الخروج بنجاح');
    }

    /**
     * Return the authenticated user's profile with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            $this->formatUser($request->user())
        );
    }

    /**
     * Format user data for API response.
     */
    private function formatUser(User $user): array
    {
        $user->load('roles.permissions');

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'username'    => $user->username,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'is_active'   => $user->is_active,
            'roles'       => $user->roles->map(fn ($role) => [
                'id'           => $role->id,
                'name'         => $role->name,
                'display_name' => $role->display_name,
            ])->values(),
            'permissions' => $user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                ->unique()
                ->values(),
        ];
    }
}
