<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\UserCompanyDirectory;
use App\Support\AccessChannel;
use App\Support\PlatformAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, AuditLogger $audit, UserCompanyDirectory $directory): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $password = $credentials['password'];

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        PlatformAdmin::syncFlagFromConfig($user);
        $user->refresh();

        $deviceName = $credentials['device_name'] ?? 'phoenix-web';
        $token = $user->createToken($deviceName)->plainTextToken;
        $access = AccessChannel::fromRequest($request, $deviceName);

        $this->recordAccessAudit($audit, $user, 'auth.login', $access, $request->ip());

        return response()->json([
            'token' => $token,
            'user' => ProfileController::formatUser($user),
            'companies' => $directory->companiesForUser($user)->values(),
        ]);
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $access = AccessChannel::fromRequest($request);

        $this->recordAccessAudit($audit, $user, 'auth.logout', $access, $request->ip());

        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request, UserCompanyDirectory $directory): JsonResponse
    {
        $user = $request->user();
        PlatformAdmin::syncFlagFromConfig($user);
        $user->refresh();

        return response()->json([
            'user' => ProfileController::formatUser($user),
            'companies' => $directory->companiesForUser($user)->values(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $access
     */
    private function recordAccessAudit(
        AuditLogger $audit,
        User $user,
        string $action,
        array $access,
        ?string $ip,
    ): void {
        $companyIds = \App\Models\CompanyMembership::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($companyIds === []) {
            $audit->record(null, $user->id, $action, User::class, $user->id, $access, $ip);

            return;
        }

        foreach ($companyIds as $companyId) {
            $audit->record($companyId, $user->id, $action, User::class, $user->id, $access, $ip);
        }
    }
}
