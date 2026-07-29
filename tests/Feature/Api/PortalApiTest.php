<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_portal_settings(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/portal/settings', [
                'contact_email' => 'nuevo@pyro-systems.com',
                'help_title' => 'Soporte',
            ])
            ->assertOk()
            ->assertJsonPath('data.contact_email', 'nuevo@pyro-systems.com')
            ->assertJsonPath('data.help_title', 'Soporte');

        $this->assertDatabaseHas('portal_settings', [
            'id' => 1,
            'contact_email' => 'nuevo@pyro-systems.com',
            'help_title' => 'Soporte',
        ]);
    }

    public function test_public_portal_returns_defaults(): void
    {
        $this->getJson('/api/v1/portal')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'hero_image_url',
                    'service_title',
                    'help_title',
                    'contact_email',
                ],
            ])
            ->assertJsonPath('data.service_title', 'Gestión técnica clara para operaciones industriales');
    }

    public function test_non_admin_cannot_update_portal_settings(): void
    {
        $this->seed();
        $tech = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($tech);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/portal/settings', [
                'contact_email' => 'hack@evil.test',
            ])
            ->assertForbidden();
    }
}
