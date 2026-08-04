<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\User;
use App\Support\DemoAccounts;

/**
 * Acceso al tenant demo operativo (Sandbox).
 *
 * Conserva el nombre histórico `UsesMeinCompany` / `meinCompany()` porque muchos
 * tests lo usan; Mein/Dom-G quedan vírgenes (solo usuarios + cliente interno).
 */
trait UsesMeinCompany
{
    protected function meinCompany(): Company
    {
        return Company::query()->where('name', 'Sandbox')->firstOrFail();
    }

    protected function meinUser(string $email): User
    {
        $company = $this->meinCompany();
        $email = $this->mapLegacyMeinEmailToSandbox($email);

        return User::query()
            ->where('email', $email)
            ->whereHas('memberships', fn ($q) => $q->where('company_id', $company->id)->where('is_active', true))
            ->firstOrFail();
    }

    protected function mapLegacyMeinEmailToSandbox(string $email): string
    {
        return match ($email) {
            'emilio.sanchez@mein-company.com' => DemoAccounts::DEFAULT_LOGIN_EMAIL,
            'misael.palos@mein-company.com' => 'technician@sandbox-demo.com',
            'claudio.rodriguez@mein-company.com' => 'supervisor@sandbox-demo.com',
            'elena.sanchez@mein-company.com' => 'billing@sandbox-demo.com',
            'cliente.portal@mein-company.com' => 'cliente.portal@sandbox-demo.com',
            default => $email,
        };
    }
}
