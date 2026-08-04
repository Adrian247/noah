<?php

namespace Tests\Feature\Api;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RoutineCreatedAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_routine_writes_audit_entry_with_correlation(): void
    {
        $this->seed();
        Mail::fake();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/routines', [
                'site_id' => \App\Models\Site::query()->where('company_id', $company->id)->value('id'),
                'asset_id' => \App\Models\Asset::query()->where('company_id', $company->id)->value('id'),
                'routine_type_id' => \App\Models\RoutineType::query()->where('company_id', $company->id)->value('id'),
                'assigned_to' => $technician->id,
            ])
            ->assertCreated();

        $routineId = $response->json('data.id');
        $correlationId = $response->json('data.workflow_instance.correlation_id');
        $this->assertNotNull($correlationId);

        $entry = AuditEntry::query()
            ->where('action', 'routine.created')
            ->where('subject_id', $routineId)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($admin->id, $entry->actor_user_id);
        $this->assertSame($correlationId, $entry->correlation_id);
        $this->assertSame($routineId, $entry->metadata['routine_id'] ?? null);
        $this->assertSame($technician->id, $entry->metadata['assigned_to'] ?? null);
        $this->assertFalse((bool) ($entry->metadata['is_demo'] ?? true));
    }

    public function test_demo_routine_creation_is_audited_as_demo(): void
    {
        $this->seed();
        Mail::fake();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/routines/demo')
            ->assertCreated();

        $routineId = $response->json('data.id');
        $correlationId = $response->json('data.workflow_instance.correlation_id')
            ?? \App\Models\WorkflowInstance::query()->where('routine_id', $routineId)->value('correlation_id');

        $entry = AuditEntry::query()
            ->where('action', 'routine.created')
            ->where('subject_id', $routineId)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($admin->id, $entry->actor_user_id);
        $this->assertSame($correlationId, $entry->correlation_id);
        $this->assertTrue((bool) ($entry->metadata['is_demo'] ?? false));
    }
}
