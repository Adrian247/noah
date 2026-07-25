<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_admin_can_create_client(): void
    {
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/clients', [
                'legal_name' => 'Nuevo Cliente S.A.',
                'code' => 'NC-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.legal_name', 'Nuevo Cliente S.A.');
    }

    public function test_technician_cannot_create_client(): void
    {
        $company = Company::query()->first();
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/clients', ['legal_name' => 'X'])
            ->assertForbidden();
    }

    public function test_billing_can_list_clients(): void
    {
        $company = Company::query()->first();
        $billing = User::query()->where('email', 'facturacion@noah.local')->first();
        Sanctum::actingAs($billing);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'legal_name', 'logo_url']]]);
    }

    public function test_admin_can_upload_client_logo(): void
    {
        Storage::fake('public');
        $company = Company::query()->first();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        Sanctum::actingAs($admin);

        $client = Client::query()->first();
        $this->assertNotNull($client);

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/clients/'.$client->id.'/logo', ['logo' => $file])
            ->assertOk()
            ->assertJsonStructure(['data' => ['logo_url']]);

        $client->refresh();
        $this->assertNotNull($client->logo_path);
    }
}
