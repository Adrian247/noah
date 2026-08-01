<?php

namespace Tests\Unit\Services\AI;

use App\Models\AiInvocation;
use App\Services\AI\AiGateway;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class AiGatewayAssistantTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_local_assistant_uses_tools_and_logs_invocation(): void
    {
        config(['phoenix.ai.default_provider' => 'local']);

        $company = $this->meinCompany();
        $admin = $this->meinUser('emilio.sanchez@mein-company.com');
        $result = app(AiGateway::class)->invokeAssistant('¿Qué rutinas hay activas?', $company->id, $admin);

        $this->assertNotNull($result);
        $this->assertSame('local', $result['provider']);
        $this->assertNotEmpty($result['tool_calls']);
        $this->assertSame('list_recent_routines', $result['tool_calls'][0]['name']);
        $this->assertTrue($result['tool_calls'][0]['ok']);
        $this->assertStringContainsString('Rutinas recientes', $result['answer']);
        $this->assertArrayHasKey('conversation_id', $result);
        $this->assertNotEmpty($result['conversation_id']);
        $this->assertArrayHasKey('presentation', $result);

        $this->assertDatabaseHas('ai_invocations', [
            'company_id' => $company->id,
            'use_case' => 'insights_assistant',
            'provider' => 'local',
            'status' => 'success',
        ]);

        $invocation = AiInvocation::query()
            ->where('company_id', $company->id)
            ->where('use_case', 'insights_assistant')
            ->latest('id')
            ->first();

        $this->assertNotNull($invocation);
        $this->assertIsArray($invocation->tool_calls);
        $this->assertNotEmpty($invocation->tool_calls);
    }

    public function test_local_assistant_builds_kpi_dashboard_presentation(): void
    {
        config(['phoenix.ai.default_provider' => 'local']);

        $company = $this->meinCompany();
        $admin = $this->meinUser('emilio.sanchez@mein-company.com');
        $result = app(AiGateway::class)->invokeAssistant(
            'Muéstrame el dashboard de KPIs',
            $company->id,
            $admin,
        );

        $this->assertNotNull($result);
        $this->assertSame('local', $result['provider']);
        $this->assertNotEmpty($result['tool_calls']);
        $this->assertSame('get_operational_kpis', $result['tool_calls'][0]['name']);
        $this->assertNotNull($result['presentation']);
        $this->assertSame('dashboard', $result['presentation']['type']);
        $this->assertNotEmpty($result['presentation']['content']['charts'] ?? []);
    }

    public function test_local_assistant_lists_clients_when_asked(): void
    {
        config(['phoenix.ai.default_provider' => 'local']);

        $company = $this->meinCompany();
        $admin = $this->meinUser('emilio.sanchez@mein-company.com');
        $result = app(AiGateway::class)->invokeAssistant('Lista clientes', $company->id, $admin);

        $this->assertNotNull($result);
        $this->assertSame('list_clients', $result['tool_calls'][0]['name'] ?? null);
        $this->assertTrue($result['tool_calls'][0]['ok'] ?? false);
    }
}
