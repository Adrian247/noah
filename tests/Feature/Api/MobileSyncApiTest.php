<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesDemoRoutine;
use Tests\Support\UsesMeinCompany;
use Tests\Support\VehicleDemoFormResponses;
use Tests\TestCase;

class MobileSyncApiTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;
    use UsesMeinCompany;

    public function test_sync_push_is_idempotent(): void
    {
        $this->seed();
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $company = $this->meinCompany();
        $routine = $this->demoRoutine($technician);

        Sanctum::actingAs($technician);

        $payload = [
            'device_id' => 'test-device-1',
            'pull' => false,
            'events' => [
                [
                    'event_id' => 'evt-001',
                    'event_type' => 'execution.submitted',
                    'payload' => [
                        'routine_id' => $routine->id,
                        'technician_comments' => 'desde movil',
                        'duration_minutes' => 45,
                        'responses' => VehicleDemoFormResponses::required(),
                        'consumptions' => [],
                    ],
                ],
            ],
        ];

        $headers = ['X-Company-Id' => (string) $company->id];

        $this->postJson('/api/v1/sync', $payload, $headers)->assertOk()
            ->assertJsonPath('data.push.accepted', ['evt-001']);

        $routine->refresh();
        $this->assertSame('pending_validation', $routine->status->value);

        $this->postJson('/api/v1/sync', $payload, $headers)->assertOk()
            ->assertJsonPath('data.push.accepted', ['evt-001']);
    }

    public function test_sync_pull_returns_assigned_routines(): void
    {
        $this->seed();
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $company = $this->meinCompany();

        Sanctum::actingAs($technician);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/sync', [
                'device_id' => 'test-device-2',
                'events' => [],
                'pull' => true,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pull' => ['routines', 'routine_types', 'server_time', 'mobile_policy', 'supply_items'],
                ],
            ]);
    }

    public function test_sync_pull_includes_site_client_and_asset_location(): void
    {
        $this->seed();
        $technician = $this->meinUser('technician@sandbox-demo.com');
        $company = $this->meinCompany();
        $routine = $this->demoRoutine($technician);
        $routine->loadMissing(['asset', 'client', 'site']);

        Sanctum::actingAs($technician);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/sync', [
                'device_id' => 'test-device-context',
                'events' => [],
                'pull' => true,
            ])
            ->assertOk();

        $pulled = collect($response->json('data.pull.routines'))
            ->firstWhere('id', $routine->id);

        $this->assertIsArray($pulled);
        $this->assertArrayHasKey('site', $pulled);
        $this->assertArrayHasKey('client', $pulled);
        $this->assertSame($routine->site?->name, $pulled['site']['name'] ?? null);
        if ($routine->asset !== null) {
            $this->assertSame($routine->asset->tag, $pulled['asset']['tag'] ?? null);
            $this->assertArrayHasKey('location_label', $pulled['asset']);
            $this->assertArrayHasKey('serial_number', $pulled['asset']);
        }
    }
}
