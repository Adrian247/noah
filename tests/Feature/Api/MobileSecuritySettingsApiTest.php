<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileSecuritySettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_update_mobile_security_settings(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'emilio.sanchez@mein-company.com')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/mobile/settings', [
                'mobile_require_app_lock' => true,
                'mobile_allow_biometric_unlock' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.mobile_require_app_lock', true)
            ->assertJsonPath('data.mobile_allow_biometric_unlock', false);

        $company->refresh();
        $this->assertTrue($company->mobile_require_app_lock);
        $this->assertFalse($company->mobile_allow_biometric_unlock);
    }

    public function test_technician_cannot_update_mobile_security_settings(): void
    {
        $this->seed();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/mobile/settings', [
                'mobile_require_app_lock' => true,
                'mobile_allow_biometric_unlock' => true,
            ])
            ->assertForbidden();
    }
}
