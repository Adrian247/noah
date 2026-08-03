<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PlatformAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json([
            'user' => self::formatUser($user->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => self::avatarUrl($user),
            'is_platform_admin' => PlatformAdmin::isPlatformAdmin($user),
        ];
    }

    public static function avatarUrl(User $user): ?string
    {
        if ($user->avatar_path === null || $user->avatar_path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($user->avatar_path)) {
            return null;
        }

        return Storage::disk('public')->url($user->avatar_path);
    }
}
