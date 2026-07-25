<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
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
