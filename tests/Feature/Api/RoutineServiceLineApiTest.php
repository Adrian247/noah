<?php

namespace Tests\Feature\Api;

use App\Enums\ServiceCategory;
use App\Models\Client;
use App\Models\RoutineType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class RoutineServiceLineApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->companyId = (int) $this->meinCompany()->id;
        Sanctum::actingAs(User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail());
    }

    public function test_manufacturing_service_requires_client_and_allows_null_asset(): void
    {
        $type = RoutineType::withoutGlobalScope('company')
            ->where('company_id', $this->companyId)
            ->where('service_category', ServiceCategory::Manufacturing)
            ->firstOrFail();

        $client = Client::withoutGlobalScope('company')
            ->where('company_id', $this->companyId)
            ->where('code', 'SANDBOX-CLI-001')
            ->firstOrFail();

        $site = Site::withoutGlobalScope('company')
            ->where('company_id', $this->companyId)
            ->firstOrFail();

        $this->withHeader('X-Company-Id', (string) $this->companyId)
            ->postJson('/api/v1/routines', [
                'site_id' => $site->id,
                'routine_type_id' => $type->id,
                'client_id' => $client->id,
                'asset_id' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.asset_id', null)
            ->assertJsonPath('data.routine_type.service_category', 'manufacturing');
    }

    public function test_maintenance_service_requires_asset(): void
    {
        $type = RoutineType::withoutGlobalScope('company')
            ->where('company_id', $this->companyId)
            ->where('service_category', ServiceCategory::Maintenance)
            ->firstOrFail();

        $site = Site::withoutGlobalScope('company')
            ->where('company_id', $this->companyId)
            ->firstOrFail();

        $this->withHeader('X-Company-Id', (string) $this->companyId)
            ->postJson('/api/v1/routines', [
                'site_id' => $site->id,
                'routine_type_id' => $type->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['asset_id']);
    }
}
