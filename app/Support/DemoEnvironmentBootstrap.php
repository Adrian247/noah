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

        if (User::query()->where('email', 'admin@noah.local')->exists()) {
            return false;
        }

        $ran = false;

        Cache::lock('noah-demo-bootstrap', 120)->block(30, function () use (&$ran): void {
            if (User::query()->where('email', 'admin@noah.local')->exists()) {
                return;
            }

            Log::warning('Demo admin missing; running noah:refresh-demo (--skip-migrate).');

            Artisan::call('noah:refresh-demo', ['--skip-migrate' => true]);
            $ran = true;
        });

        return $ran;
    }
}
