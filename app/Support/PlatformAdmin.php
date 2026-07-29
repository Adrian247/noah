<?php

namespace App\Support;

use App\Models\User;

class PlatformAdmin
{
    public static function isPlatformAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_platform_admin) {
            return true;
        }

        $allowed = config('phoenix.platform_admin_emails', []);

        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        $email = strtolower(trim($user->email));

        foreach ($allowed as $entry) {
            if (is_string($entry) && strtolower(trim($entry)) === $email) {
                return true;
            }
        }

        return false;
    }

    public static function syncFlagFromConfig(User $user): void
    {
        if (! self::isPlatformAdmin($user)) {
            return;
        }

        if (! $user->is_platform_admin) {
            $user->forceFill(['is_platform_admin' => true])->save();
        }
    }
}
