<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\Workflow\WorkflowBlockCompiler;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowBlockDesignerApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, company: Company}
     */
    private function adminContext(): array
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->firstOrFail();

        return [
            'token' => $admin->createToken('test')->plainTextToken,
            'company' => $company,
        ];
    }

    public function test_show_auto_upgrades_legacy_definition_to_block_graph_v2(): void
    {
        ['token' => $token, 'company' => $company] = $this->adminContext();

        $legacy = WorkflowRuntime::defaultDefinition();
        unset($legacy['meta']['block_graph']);
        $legacy['meta']['block_editor_version'] = 1;

        $workflow = WorkflowDefinition::query()->create([
            'company_id' => $company->id,
            'name' => 'Legacy sin bloques',
            'slug' => 'legacy-sin-bloques',
            'version' => 1,
            'status' => 'draft',
            'definition' => $legacy,
        ]);

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson("/api/v1/design/workflows/{$workflow->id}")
            ->assertOk();

        $definition = $response->json('data.definition');
        $this->assertSame(2, $definition['meta']['block_editor_version'] ?? null);
        $this->assertArrayHasKey('block_graph', $definition['meta'] ?? []);
        $this->assertNotEmpty($definition['meta']['block_graph']['nodes'] ?? []);

        $workflow->refresh();
        $this->assertSame(2, $workflow->definition['meta']['block_editor_version'] ?? null);
    }

    public function test_put_definition_compiles_block_graph_into_transitions(): void
    {
        ['token' => $token, 'company' => $company] = $this->adminContext();

        $create = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/workflows', ['name' => 'Bloques API'])
            ->assertCreated();

        $workflowId = (int) $create->json('data.id');
        $graph = WorkflowBlockCompiler::defaultGraph();
        foreach ($graph['edges'] as $index => $edge) {
            if (($edge['action'] ?? '') === 'submit') {
                $graph['edges'][$index]['label'] = 'Enviar a revisión';
            }
        }

        $definition = WorkflowRuntime::defaultDefinition();
        $definition['meta']['block_editor_version'] = 2;
        $definition['meta']['block_graph'] = $graph;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/workflows/{$workflowId}/definition", [
                'definition' => $definition,
            ])
            ->assertOk();

        $workflow = WorkflowDefinition::query()->findOrFail($workflowId);
        $submit = collect($workflow->definition['transitions'] ?? [])->first(
            fn (array $transition) => ($transition['trigger'] ?? '') === 'execution_submitted',
        );

        $this->assertSame('Enviar a revisión', $submit['label'] ?? '');
        $storedEdge = collect($workflow->definition['meta']['block_graph']['edges'] ?? [])->first(
            fn (array $edge) => ($edge['action'] ?? '') === 'submit',
        );
        $this->assertSame('Enviar a revisión', $storedEdge['label'] ?? '');
    }
}
