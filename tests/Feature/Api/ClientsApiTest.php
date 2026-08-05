<?php

namespace Tests\Feature\Api;

use App\Enums\MembershipRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Identity\ClientPortalAccountService;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientsApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
        $this->company = Company::query()->where('name', 'Sandbox')->firstOrFail();
        $this->admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
    }

    public function test_admin_can_create_client_with_portal_account(): void
    {
        Sanctum::actingAs($this->admin);

        $email = 'nuevo.portal@example.com';

        $this->withHeader('X-Company-Id', (string) $this->company->id)
            ->postJson('/api/v1/clients', [
                'legal_name' => 'Nuevo Cliente S.A.',
                'code' => 'NC-01',
                'billing_email' => $email,
            ])
            ->assertCreated()
            ->assertJsonPath('data.legal_name', 'Nuevo Cliente S.A.')
            ->assertJsonPath('data.billing_email', $email)
            ->assertJsonPath('data.portal_login_email', $email)
            ->assertJsonPath('data.portal_password_hint', ClientPortalAccountService::portalPassword());

        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);

        $membership = CompanyMembership::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame(MembershipRole::Client, $membership->role);
        $this->assertTrue((bool) $membership->is_active);
        $this->assertTrue(password_verify(ClientPortalAccountService::portalPassword(), $user->password));
    }

    public function test_create_client_requires_billing_email(): void
    {
        Sanctum::actingAs($this->admin);

        $this->withHeader('X-Company-Id', (string) $this->company->id)
            ->postJson('/api/v1/clients', [
                'legal_name' => 'Sin correo',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_email']);
    }

    public function test_technician_cannot_create_client(): void
    {
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $this->company->id)
            ->postJson('/api/v1/clients', [
                'legal_name' => 'X',
                'billing_email' => 'x@example.com',
            ])
            ->assertForbidden();
    }

    public function test_billing_can_list_clients(): void
    {
        $billing = User::query()->where('email', 'billing@sandbox-demo.com')->firstOrFail();
        Sanctum::actingAs($billing);

        $this->withHeader('X-Company-Id', (string) $this->company->id)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'legal_name', 'logo_url', 'portal_login_email']]]);
    }

    public function test_admin_can_upload_client_logo(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin);

        $client = Client::query()->where('company_id', $this->company->id)->first();
        $this->assertNotNull($client);

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->withHeader('X-Company-Id', (string) $this->company->id)
            ->postJson('/api/v1/clients/'.$client->id.'/logo', ['logo' => $file])
            ->assertOk()
            ->assertJsonStructure(['data' => ['logo_url']]);

        $client->refresh();
        $this->assertNotNull($client->logo_path);
    }
}
