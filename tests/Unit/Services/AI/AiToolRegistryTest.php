<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\Tools\AiToolRegistry;
use App\Services\AI\Tools\GetRoutineTool;
use App\Services\AI\Tools\ListAuditEntriesTool;
use App\Services\AI\Tools\ListRecentRoutinesTool;
use App\Services\AI\Tools\ListSupplyItemsTool;
use App\Services\AI\Tools\SearchAssetsTool;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class AiToolRegistryTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_registry_exposes_read_only_tools_and_scopes_by_company(): void
    {
        $registry = app(AiToolRegistry::class);

        $this->assertEqualsCanonicalizing([
            'list_recent_routines',
            'get_routine',
            'list_audit_entries',
            'search_assets',
            'list_supply_items',
            'list_clients',
            'list_invoices',
            'list_sites',
            'get_operational_kpis',
        ], $registry->names());

        $schemas = $registry->openAiToolSchemas();
        $this->assertCount(9, $schemas);
        $this->assertSame('function', $schemas[0]['type']);

        $company = $this->meinCompany();
        $result = $registry->execute('list_recent_routines', ['limit' => 3], $company->id);

        $this->assertIsArray($result['data']);
        $this->assertIsArray($result['sources']);
        foreach ($result['sources'] as $source) {
            $this->assertSame('routine', $source['type']);
            $this->assertArrayHasKey('id', $source);
            $this->assertArrayHasKey('label', $source);
        }
    }

    public function test_get_routine_returns_error_payload_when_missing(): void
    {
        $registry = new AiToolRegistry([
            new ListRecentRoutinesTool,
            new GetRoutineTool,
            new ListAuditEntriesTool,
            new SearchAssetsTool,
            new ListSupplyItemsTool,
        ]);

        $company = $this->meinCompany();
        $result = $registry->execute('get_routine', ['routine_id' => 999999], $company->id);

        $this->assertSame([], $result['sources']);
        $this->assertArrayHasKey('error', $result['data']);
    }
}
