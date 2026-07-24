<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, AuditLogger $audit): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'noah-web')->plainTextToken;

        $audit->record(
            null,
            $user->id,
            'auth.login',
            User::class,
            $user->id,
            [],
            $request->ip(),
        );

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'companies' => $user->memberships()
                ->where('is_active', true)
                ->with('company')
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->company->id,
                    'name' => $m->company->name,
                    'role' => $m->role->value,
                ]),
        ]);
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $audit->record(
            null,
            $user->id,
            'auth.logout',
            User::class,
            $user->id,
            [],
            $request->ip(),
        );

        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'companies' => $user->memberships()
                ->where('is_active', true)
                ->with('company')
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->company->id,
                    'name' => $m->company->name,
                    'role' => $m->role->value,
                ]),
        ]);
    }
}
