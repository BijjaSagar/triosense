<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

final class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        Log::info('AuthController.login.attempt', ['email' => $credentials['email']]);

        /** @var User|null $user */
        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', $credentials['email'])
            ->where('status', 'active')
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            Log::warning('AuthController.login.failed', ['email' => $credentials['email']]);

            return $this->errorResponse(
                code: 'invalid_credentials',
                message: 'Invalid email or password.',
                status: 401,
            );
        }

        setPermissionsTeamId($user->tenant_id);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $useCookieAuth = $request->header('X-TrioSense-Auth') === 'cookie';

        if ($useCookieAuth) {
            Auth::guard('web')->login($user, remember: true);
            $request->session()->regenerate();

            Log::info('AuthController.login.success.cookie', [
                'user_id' => $user->user_id,
                'tenant_id' => $user->tenant_id,
            ]);

            return $this->successResponse([
                'token' => null,
                'user' => $this->serializeUser($user),
                'expires_at' => null,
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        Log::info('AuthController.login.success.token', [
            'user_id' => $user->user_id,
            'tenant_id' => $user->tenant_id,
        ]);

        return $this->successResponse([
            'token' => $token,
            'user' => $this->serializeUser($user),
            'expires_at' => null,
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();

        if ($user !== null) {
            Log::info('AuthController.logout', ['user_id' => $user->user_id]);

            $token = $user->currentAccessToken();
            if ($token !== null) {
                $token->delete();
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        setPermissionsTeamId($user->tenant_id);

        Log::debug('AuthController.me', ['user_id' => $user->user_id]);

        return $this->successResponse($this->serializeUser($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        setPermissionsTeamId($user->tenant_id);

        return [
            'user_id' => $user->user_id,
            'tenant_id' => $user->tenant_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'locations' => $user->assignedLocationIds(),
        ];
    }
}
