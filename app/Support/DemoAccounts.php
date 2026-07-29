<?php

namespace App\Support;

use Database\Seeders\Support\TenantDemoProfile;

class DemoAccounts
{
    public const ROOT_EMAIL = 'admin@pyro-systems.com';

    public static function rootPassword(): string
    {
        return (string) config('phoenix.demo_root_password');
    }

    public static function tenantPassword(): string
    {
        return (string) config('phoenix.demo_password');
    }

    /**
     * @return list<string>
     */
    public static function allEmails(): array
    {
        $emails = [self::ROOT_EMAIL];

        foreach ([TenantDemoProfile::mein(), TenantDemoProfile::domG()] as $profile) {
            foreach ($profile->staff as $row) {
                $emails[] = strtolower($row['email']);
            }
        }

        return array_values(array_unique($emails));
    }

    public static function passwordForEmail(string $email): string
    {
        return strtolower(trim($email)) === self::ROOT_EMAIL
            ? self::rootPassword()
            : self::tenantPassword();
    }
}
