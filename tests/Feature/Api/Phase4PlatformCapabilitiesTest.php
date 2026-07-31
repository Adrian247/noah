<?php

namespace Tests\Feature\Api;

use App\Jobs\DispatchWebhookJob;
use App\Models\AutomationRule;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Services\Identity\CompanyAuthorizationService;
use App\Services\Routines\DemoRoutineFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesMeinCompany;
use Tests\Support\VehicleDemoFormResponses;
use Tests\TestCase;

class Phase4PlatformCapabilitiesTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_webhook_and_automation_crud(): void
    {
        $company = $this->meinCompany();
        $admin = $this->meinUser('emilio.sanchez@mein-company.com');
        Sanctum::actingAs($admin);

        $create = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/integrations/webhooks', [
                'name' => 'ERP hook',
                'url' => 'https://example.com/hooks/phoenix',
                'events' => ['routine.validated'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'ERP hook');

        $webhookId = (int) $create->json('data.id');
        $this->assertNotEmpty($create->json('data.secret'));

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/integrations/webhooks')
            ->assertOk()
            ->assertJsonPath('data.0.id', $webhookId);

        $rule = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/automation/rules', [
                'name' => 'Log validación',
                'trigger_type' => 'routine.validated',
                'actions' => [['type' => 'log', 'message' => 'ok']],
            ])
            ->assertCreated();

        $ruleId = (int) $rule->json('data.id');

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/automation/rules/{$ruleId}", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(1, WebhookSubscription::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, AutomationRule::query()->where('company_id', $company->id)->count());
    }

    public function test_dashboard_preferences_roundtrip(): void
    {
        $company = $this->meinCompany();
        $admin = $this->meinUser('emilio.sanchez@mein-company.com');
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/dashboard/preferences')
            ->assertOk()
            ->assertJsonStructure(['data' => ['layout', 'catalog']]);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/dashboard/preferences', [
                'widgets' => ['operations', 'activity'],
            ])
            ->assertOk();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/dashboard/preferences')
            ->assertOk()
            ->assertJsonPath('data.layout', ['operations', 'activity']);
    }

    public function test_insights_assistant_returns_answer(): void
    {
        $company = $this->meinCompany();
        $admin = $this->meinUser('emilio.sanchez@mein-company.com');
        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/insights/assistant', [
                'question' => '¿Qué rutinas hay?',
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['answer', 'sources']]);
    }

    public function test_routine_validated_dispatches_webhook_job(): void
    {
        Queue::fake();

        $company = $this->meinCompany();
        $technician = $this->meinUser('misael.palos@mein-company.com');
        $supervisor = $this->meinUser('claudio.rodriguez@mein-company.com');

        WebhookSubscription::query()->create([
            'company_id' => $company->id,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'secret' => 'secret',
            'events' => ['routine.validated'],
            'is_active' => true,
        ]);

        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);

        $this->withToken($technician->createToken('t')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'Demo',
                'duration_minutes' => 30,
                'responses' => VehicleDemoFormResponses::required(),
            ])
            ->assertCreated();

        Sanctum::actingAs($supervisor);
        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/validate")
            ->assertOk();

        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_execution_evidence_upload(): void
    {
        $company = $this->meinCompany();
        $technician = $this->meinUser('misael.palos@mein-company.com');
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);

        $this->withToken($technician->createToken('t')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'Con evidencia',
                'duration_minutes' => 20,
                'responses' => VehicleDemoFormResponses::required(),
            ])
            ->assertCreated();

        $file = UploadedFile::fake()->image('evidence.jpg');

        $this->withToken($technician->createToken('t2')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->post("/api/v1/routines/{$routine->id}/evidences", [
                'file' => $file,
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'original_name']]);

        $this->withToken($technician->createToken('t3')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson("/api/v1/routines/{$routine->id}")
            ->assertOk()
            ->assertJsonPath('data.latest_execution.evidences.0.original_name', 'evidence.jpg');
    }
}
