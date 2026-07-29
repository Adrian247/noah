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

    public function test_login_creates_audit_entry_and_admin_can_list_company_audit(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@pyro-systems.com',
            'password' => config('phoenix.demo_root_password'),
        ])->assertOk();

        $this->assertDatabaseHas('audit_entries', [
            'action' => 'auth.login',
            'actor_user_id' => $admin->id,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/audit/entries')
            ->assertOk();
    }

    public function test_audit_entries_can_filter_by_correlation_id(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
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
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        $company = Company::query()->first();
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
}
