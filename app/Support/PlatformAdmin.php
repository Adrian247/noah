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

        $allowed = config('noah.platform_admin_emails', []);

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
}
