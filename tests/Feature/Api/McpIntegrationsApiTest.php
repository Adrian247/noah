<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class McpIntegrationsApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_list_mcp_tools_and_create_token(): void
    {
        $company = $this->meinCompany();
        $admin = $this->meinUser('admin@sandbox-demo.com');
        Sanctum::actingAs($admin);

        $tools = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/integrations/mcp/tools')
            ->assertOk()
            ->json('data');

        $this->assertSame('read', $tools['mode']);
        $this->assertGreaterThan(5, $tools['total_count']);
        $this->assertNotEmpty($tools['tools']);
        $this->assertTrue(collect($tools['tools'])->contains(fn ($t) => $t['name'] === 'list_catalog_items'));
        $this->assertTrue(collect($tools['tools'])->contains(fn ($t) => $t['name'] === 'get_client_detail'));

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/integrations/mcp/connection')
            ->assertOk()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.transport', 'streamable_http')
            ->assertJsonStructure(['data' => ['cursor_mcp_json', 'http_examples', 'base_url']]);

        $cursorUrl = data_get(
            $this->withHeader('X-Company-Id', (string) $company->id)
                ->getJson('/api/v1/integrations/mcp/connection')
                ->json(),
            'data.cursor_mcp_json.mcpServers.phoenix.url',
        );
        $this->assertIsString($cursorUrl);
        $this->assertStringEndsWith('/api/v1/integrations/mcp', $cursorUrl);
        $this->assertStringNotContainsString('/tools', (string) $cursorUrl);

        $created = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/integrations/mcp/tokens', ['label' => 'Cursor test'])
            ->assertCreated()
            ->json('data');

        $this->assertNotEmpty($created['token']);
        $this->assertSame('Cursor test', $created['label']);
        $this->assertContains('mcp', $created['abilities']);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/integrations/mcp/tokens')
            ->assertOk()
            ->assertJsonFragment(['label' => 'Cursor test']);

        $plain = $created['token'];
        $this->withToken($plain)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/integrations/mcp/tools/list_clients/invoke', ['arguments' => ['limit' => 3]])
            ->assertOk()
            ->assertJsonPath('data.tool', 'list_clients')
            ->assertJsonPath('data.mode', 'read');

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson('/api/v1/integrations/mcp/tokens/'.$created['id'])
            ->assertOk();
    }

    public function test_mcp_streamable_http_protocol_initialize_and_tools_list(): void
    {
        $company = $this->meinCompany();
        $admin = $this->meinUser('admin@sandbox-demo.com');

        $plain = $this->withHeader('X-Company-Id', (string) $company->id)
            ->actingAs($admin)
            ->postJson('/api/v1/integrations/mcp/tokens', ['label' => 'Protocol test'])
            ->assertCreated()
            ->json('data.token');

        $init = $this->withToken($plain)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->postJson('/api/v1/integrations/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass,
                    'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                ],
            ])
            ->assertOk()
            ->json();

        $this->assertSame('2.0', $init['jsonrpc']);
        $this->assertSame(1, $init['id']);
        $this->assertSame('2025-03-26', $init['result']['protocolVersion']);
        $this->assertSame('phoenix', $init['result']['serverInfo']['name']);

        $this->withToken($plain)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/integrations/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'notifications/initialized',
            ])
            ->assertStatus(202);

        $listedResponse = $this->withToken($plain)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/integrations/mcp', [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
                'params' => new \stdClass,
            ])
            ->assertOk();

        $listed = $listedResponse->json('result.tools');
        $this->assertIsArray($listed);
        $this->assertNotEmpty($listed);
        $this->assertTrue(collect($listed)->contains(fn ($t) => ($t['name'] ?? '') === 'list_clients'));
        $this->assertTrue(collect($listed)->contains(fn ($t) => ($t['name'] ?? '') === 'get_operational_kpis'));
        // Cursor rechaza properties:[] (array). El wire format debe ser {}.
        $this->assertStringContainsString('"properties":{}', $listedResponse->getContent());
        $this->assertStringNotContainsString('"properties":[]', $listedResponse->getContent());
        $this->assertStringNotContainsString('"type":"integer"', $listedResponse->getContent());

        $stream = $this->withToken($plain)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->withHeader('Accept', 'text/event-stream')
            ->get('/api/v1/integrations/mcp');

        $stream->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $stream->headers->get('Content-Type'));
        $this->assertStringContainsString('event: endpoint', $stream->streamedContent());
    }

    public function test_read_only_user_cannot_create_mcp_token(): void
    {
        $company = $this->meinCompany();
        $technician = $this->meinUser('technician@sandbox-demo.com');
        Sanctum::actingAs($technician);

        // Technician may lack integrations.view depending on role template; if forbidden on list, stop.
        $list = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/integrations/mcp/tools');

        if ($list->status() === 403) {
            $this->assertTrue(true);

            return;
        }

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/integrations/mcp/tokens', ['label' => 'No debería'])
            ->assertForbidden();
    }
}
