<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SitesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_site(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/sites', ['name' => 'Planta Sur', 'address' => 'Calle 2'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Planta Sur');
    }
}
