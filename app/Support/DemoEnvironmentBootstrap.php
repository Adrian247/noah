<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Repara datos demo en entorno local cuando la BD quedó vacía (p. ej. arranque sin seed).
 */
class DemoEnvironmentBootstrap
{
    public static function ensureAccountsIfMissing(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        if (User::query()->where('email', 'admin@pyro-systems.com')->exists()) {
            return false;
        }

        $ran = false;

        Cache::lock('phoenix-demo-bootstrap', 120)->block(30, function () use (&$ran): void {
            if (User::query()->where('email', 'admin@pyro-systems.com')->exists()) {
                return;
            }

            Log::warning('Demo admin missing; running phoenix:refresh-demo (--skip-migrate).');

            Artisan::call('phoenix:refresh-demo', ['--skip-migrate' => true]);
            $ran = true;
        });

        return $ran;
    }
}
