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

    public function test_admin_can_update_workflow_layout(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $company = Company::query()->first();
        $workflow = WorkflowDefinition::query()->where('company_id', $company->id)->first();
        $token = $admin->createToken('test')->plainTextToken;

        $definition = WorkflowRuntime::defaultDefinition();
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
}
