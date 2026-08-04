<?php

namespace Tests\Unit\Services\AI;

use App\Ai\Agents\OperationalAssistant;
use App\Models\User;
use App\Services\AI\AiPlatformSettingsService;
use App\Services\AI\AiToolAuthorizer;
use App\Services\AI\LaravelAiAssistantRunner;
use App\Services\AI\Tools\AiToolRegistry;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Enums\Lab;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class LaravelAiAssistantRunnerTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_is_unavailable_when_provider_is_local(): void
    {
        app(AiPlatformSettingsService::class)->update(['provider' => 'local']);

        $this->assertFalse(app(LaravelAiAssistantRunner::class)->isAvailable());
    }

    public function test_runs_via_laravel_ai_agent_when_faked(): void
    {
        config([
            'phoenix.ai.openai.api_key' => 'test-key',
            'ai.providers.openai.key' => 'test-key',
        ]);
        app(AiPlatformSettingsService::class)->update([
            'provider' => 'openai',
            'openai_model' => 'gpt-4o-mini',
        ]);

        OperationalAssistant::fake([
            'Rutinas recientes según tools: #1 operativa.',
        ]);

        $company = $this->meinCompany();
        /** @var User $admin */
        $admin = $this->meinUser('admin@sandbox-demo.com');

        $result = app(LaravelAiAssistantRunner::class)->run(
            '¿Qué rutinas hay?',
            $company->id,
            $admin,
        );

        $this->assertSame('Rutinas recientes según tools: #1 operativa.', $result['answer']);
        $this->assertSame(Lab::OpenAI->value, $result['provider']);

        OperationalAssistant::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '¿Qué rutinas hay?'));
    }

    public function test_phoenix_domain_tool_adapter_is_permission_aware(): void
    {
        $company = $this->meinCompany();
        $admin = $this->meinUser('admin@sandbox-demo.com');
        $authorizer = app(AiToolAuthorizer::class);
        $registry = app(AiToolRegistry::class);

        $allowed = $authorizer->allowedTools($admin, $company->id, $registry);
        $this->assertNotEmpty($allowed);
        $this->assertContains('list_recent_routines', array_map(fn ($t) => $t->name(), $allowed));
    }
}
