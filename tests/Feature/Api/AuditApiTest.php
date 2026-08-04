<?php

namespace Tests\Feature\Api;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\User;
use App\Services\Routines\DemoRoutineFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_audit_entry_with_access_channel_and_admin_can_list(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $company = Company::query()->where('name', 'Sandbox')->firstOrFail();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@sandbox-demo.com',
            'password' => config('phoenix.demo_password'),
            'device_name' => 'phoenix-web',
        ])->assertOk();

        $entry = AuditEntry::query()
            ->where('action', 'auth.login')
            ->where('actor_user_id', $admin->id)
            ->where('company_id', $company->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('web', $entry->metadata['access_channel'] ?? null);
        $this->assertSame('phoenix-web', $entry->metadata['device_name'] ?? null);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/entries')
            ->assertOk()
            ->assertJsonFragment(['action' => 'auth.login']);
    }

    public function test_mobile_login_records_mobile_access_channel(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@sandbox-demo.com',
            'password' => config('phoenix.demo_password', config('phoenix.demo_root_password')),
            'device_name' => 'phoenix-field',
        ])->assertOk();

        $entry = AuditEntry::query()
            ->where('action', 'auth.login')
            ->where('actor_user_id', $admin->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('mobile', $entry->metadata['access_channel'] ?? null);
    }

    public function test_audit_entries_can_filter_by_actor_user_id(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $company = Company::query()->where('name', 'Sandbox')->firstOrFail();

        AuditEntry::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $admin->id,
            'action' => 'test.by_admin',
            'occurred_at' => now(),
        ]);
        AuditEntry::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $technician->id,
            'action' => 'test.by_tech',
            'occurred_at' => now(),
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/entries?actor_user_id='.$technician->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'test.by_tech')
            ->assertJsonPath('data.0.actor.id', $technician->id);
    }

    public function test_audit_entries_can_filter_by_access_channel(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $company = Company::query()->where('name', 'Sandbox')->firstOrFail();

        AuditEntry::query()->create([
            'company_id' => null,
            'actor_user_id' => $admin->id,
            'action' => 'auth.login',
            'metadata' => ['access_channel' => 'mobile', 'device_name' => 'phoenix-field'],
            'occurred_at' => now(),
        ]);
        AuditEntry::query()->create([
            'company_id' => $company->id,
            'actor_user_id' => $admin->id,
            'action' => 'routine.created',
            'metadata' => ['access_channel' => 'web'],
            'occurred_at' => now(),
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/entries?access_channel=mobile')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'auth.login')
            ->assertJsonPath('data.0.access_channel', 'mobile')
            ->assertJsonPath('data.0.access_channel_label', 'App móvil');
    }

    public function test_audit_entries_can_filter_by_correlation_id(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $correlationId = '11111111-1111-4111-8111-111111111111';

        AuditEntry::query()->create([
            'company_id' => $company->id,
            'action' => 'test.correlated',
            'correlation_id' => $correlationId,
            'occurred_at' => now(),
        ]);
        AuditEntry::query()->create([
            'company_id' => $company->id,
            'action' => 'test.other',
            'correlation_id' => '22222222-2222-4222-8222-222222222222',
            'occurred_at' => now(),
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/entries?correlation_id='.$correlationId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'test.correlated');
    }

    public function test_audit_threads_group_by_correlation_and_include_routine_context(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $company = Company::query()->where('name', 'Sandbox')->firstOrFail();
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $correlationId = $routine->workflowInstance?->correlation_id;
        $this->assertNotNull($correlationId);

        AuditEntry::query()->create([
            'company_id' => $company->id,
            'action' => 'workflow.transitioned',
            'correlation_id' => $correlationId,
            'metadata' => ['routine_id' => $routine->id, 'from' => 'assigned', 'to' => 'in_progress'],
            'occurred_at' => now()->subMinute(),
        ]);
        AuditEntry::query()->create([
            'company_id' => $company->id,
            'action' => 'workflow.transitioned',
            'correlation_id' => $correlationId,
            'metadata' => ['routine_id' => $routine->id, 'from' => 'in_progress', 'to' => 'complete'],
            'occurred_at' => now(),
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/threads?q='.$routine->id)
            ->assertOk()
            ->assertJsonPath('data.0.correlation_id', $correlationId)
            ->assertJsonPath('data.0.events_count', 2)
            ->assertJsonPath('data.0.routine.id', $routine->id);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/entries?routine_id='.$routine->id)
            ->assertOk()
            ->assertJsonPath('data.0.routine.id', $routine->id);
    }

    public function test_platform_tenant_assumed_is_scoped_to_company(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->where('name', 'Sandbox')->firstOrFail();
        $this->assertTrue((bool) $admin->is_platform_admin);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/platform/tenants/'.$company->id.'/assume')
            ->assertOk();

        $this->assertDatabaseHas('audit_entries', [
            'action' => 'platform.tenant_assumed',
            'company_id' => $company->id,
            'actor_user_id' => $admin->id,
            'subject_id' => $company->id,
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/entries?action=platform.tenant_assumed')
            ->assertOk()
            ->assertJsonFragment(['action' => 'platform.tenant_assumed']);
    }
}
