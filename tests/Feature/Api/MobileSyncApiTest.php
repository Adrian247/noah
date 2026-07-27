<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\VehicleDemoFormResponses;
use Tests\TestCase;

class MobileSyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_push_is_idempotent(): void
    {
        $this->seed();
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
        $company = Company::query()->first();
        $routine = Routine::query()->first();

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
        $technician = User::query()->where('email', 'tecnico@noah.local')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($technician);

        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/sync', [
                'device_id' => 'test-device-2',
                'events' => [],
                'pull' => true,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['pull' => ['routines', 'routine_types', 'server_time']]]);
    }
}
