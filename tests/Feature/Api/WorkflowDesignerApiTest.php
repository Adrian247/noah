<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowDesignerApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(Company $company): array
    {
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();

        return [
            'token' => $admin->createToken('test')->plainTextToken,
            'company' => $company,
        ];
    }

    public function test_admin_can_update_workflow_layout(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());

        $create = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/workflows', ['name' => 'Borrador layout'])
            ->assertCreated();

        $workflow = WorkflowDefinition::query()->findOrFail($create->json('data.id'));

        $definition = $workflow->definition;
        $definition['layout']['nodes']['field_execution'] = ['x' => 100, 'y' => 50];

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/workflows/{$workflow->id}/definition", [
                'definition' => $definition,
            ])
            ->assertOk();

        $workflow->refresh();
        $this->assertSame(100, $workflow->definition['layout']['nodes']['field_execution']['x']);
    }

    public function test_admin_can_create_workflow(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/workflows', [
                'name' => 'Flujo alternativo',
                'template' => 'classic_no_billing',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Flujo alternativo')
            ->assertJsonPath('data.slug', 'flujo-alternativo')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.routine_types_count', 0);

        $this->assertDatabaseHas('workflow_definitions', [
            'company_id' => $company->id,
            'name' => 'Flujo alternativo',
            'slug' => 'flujo-alternativo',
        ]);
    }

    public function test_admin_can_duplicate_workflow(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());
        $workflow = WorkflowDefinition::query()->where('company_id', $company->id)->first();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/design/workflows/{$workflow->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.name', $workflow->name.' (copia)');

        $this->assertSame(
            2,
            WorkflowDefinition::query()->where('company_id', $company->id)->count()
        );
    }

    public function test_invalid_definition_returns_422(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());
        $workflow = WorkflowDefinition::query()->where('company_id', $company->id)->first();

        $definition = WorkflowRuntime::defaultDefinition();
        unset($definition['transitions'][0]);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/workflows/{$workflow->id}/definition", [
                'definition' => $definition,
            ])
            ->assertUnprocessable();
    }

    public function test_index_includes_routine_types_count(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/design/workflows')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'version', 'status', 'routine_types_count'],
                ],
            ]);
    }

    public function test_non_admin_cannot_create_workflow(): void
    {
        $this->seed();
        $company = Company::query()->where('name', 'Mein Company')->firstOrFail();
        $user = User::query()->where('email', 'emilio.sanchez@mein-company.com')->firstOrFail();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/workflows', ['name' => 'No permitido'])
            ->assertForbidden();
    }

    public function test_publish_workflow_and_assign_to_routine_type(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());

        $create = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/workflows', [
                'name' => 'Sin facturación',
                'template' => 'validation_only',
            ])
            ->assertCreated();

        $id = $create->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/design/workflows/{$id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $draft = WorkflowDefinition::query()->findOrFail($id);
        $this->assertSame('published', $draft->status);
    }

    public function test_cannot_edit_definition_when_published(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());
        $workflow = WorkflowDefinition::query()->where('company_id', $company->id)->first();
        $this->assertSame('published', $workflow->status);

        $definition = WorkflowRuntime::defaultDefinition();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/workflows/{$workflow->id}/definition", [
                'definition' => $definition,
            ])
            ->assertUnprocessable();
    }

    public function test_configure_standard_billing_keeps_block_graph(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());

        $create = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/workflows', ['name' => 'Con email'])
            ->assertCreated();

        $id = $create->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/workflows/{$id}/configure", [
                'options' => [
                    'include_billing' => true,
                    'include_email_step' => true,
                    'routine_validated_on_approve' => true,
                    'dual_review' => false,
                ],
            ])
            ->assertOk();

        $workflow = WorkflowDefinition::query()->findOrFail($id);
        $this->assertArrayHasKey('block_graph', $workflow->definition['meta'] ?? []);
        $this->assertArrayHasKey('billing_review', $workflow->definition['steps']);
    }

    public function test_admin_can_delete_unused_draft_workflow(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());

        $create = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/workflows', ['name' => 'Temporal borrar'])
            ->assertCreated();

        $id = $create->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson("/api/v1/design/workflows/{$id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('workflow_definitions', ['id' => $id]);
    }

    public function test_cannot_delete_workflow_assigned_to_routine_type(): void
    {
        $this->seed();
        ['token' => $token, 'company' => $company] = $this->adminToken(Company::query()->first());
        $workflow = WorkflowDefinition::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertGreaterThan(0, $workflow->routineTypes()->count());

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson("/api/v1/design/workflows/{$workflow->id}")
            ->assertStatus(422);
    }
}
