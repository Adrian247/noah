<?php

namespace Tests\Unit\Services\Workflow;

use App\Services\Workflow\WorkflowBlockCompiler;
use App\Services\Workflow\WorkflowDefinitionValidator;
use Tests\TestCase;

class WorkflowBlockCompilerTest extends TestCase
{
    public function test_default_graph_compiles_and_validates(): void
    {
        $compiler = app(WorkflowBlockCompiler::class);
        $definition = $compiler->compile(WorkflowBlockCompiler::defaultGraph());
        app(WorkflowDefinitionValidator::class)->validate($definition);

        $this->assertSame('field_execution', $definition['initial_step']);
        $this->assertArrayHasKey('supervisor_review', $definition['steps']);
        $this->assertArrayHasKey('billing_review', $definition['steps']);

        $invoice = collect($definition['transitions'])->first(
            fn (array $t) => ($t['trigger'] ?? '') === 'invoice_issued',
        );
        $this->assertNotNull($invoice);
        $this->assertSame('complete', $invoice['to']);
        $this->assertNotEmpty($invoice['notify']['enabled'] ?? false);
    }

    public function test_submit_edge_carries_notify_for_technician(): void
    {
        $compiler = app(WorkflowBlockCompiler::class);
        $definition = $compiler->compile(WorkflowBlockCompiler::defaultGraph());

        $submit = collect($definition['transitions'])->first(
            fn (array $t) => ($t['trigger'] ?? '') === 'execution_submitted',
        );
        $this->assertSame('Revisión', $submit['label'] ?? '');
        $this->assertContains('executing_technician', $submit['notify']['recipients'] ?? []);
    }

    public function test_reject_to_non_routine_fails(): void
    {
        $compiler = app(WorkflowBlockCompiler::class);
        $graph = [
            'nodes' => [
                ['id' => 'field_execution', 'kind' => 'routine', 'label' => 'Rutina', 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'supervisor_review', 'kind' => 'role', 'label' => 'S', 'assigned_role' => 'supervisor', 'position' => ['x' => 1, 'y' => 0]],
                ['id' => 'complete', 'kind' => 'end', 'label' => 'Fin', 'position' => ['x' => 2, 'y' => 0]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'supervisor_review', 'target' => 'complete', 'action' => 'reject', 'label' => 'X'],
            ],
        ];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $compiler->compile($graph);
    }
}
