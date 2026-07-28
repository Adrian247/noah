<?php

namespace Tests\Unit\Services\Workflow;

use App\Enums\WorkflowTemplate;
use App\Services\Workflow\WorkflowDefinitionFactory;
use App\Services\Workflow\WorkflowDefinitionValidator;
use Tests\TestCase;

class WorkflowDefinitionFactoryTest extends TestCase
{
    public function test_classic_template_has_no_billing_step(): void
    {
        $factory = app(WorkflowDefinitionFactory::class);
        $definition = $factory->build(WorkflowTemplate::ClassicNoBilling);
        $validator = app(WorkflowDefinitionValidator::class);

        $validator->validate($definition);

        $this->assertArrayNotHasKey('billing_review', $definition['steps']);
        $this->assertTrue(
            collect($definition['transitions'])->contains(
                fn (array $t) => $t['trigger'] === 'approved' && $t['to'] === 'complete',
            ),
        );
    }

    public function test_standard_template_uses_block_graph_with_transition_notify(): void
    {
        $factory = app(WorkflowDefinitionFactory::class);
        $definition = $factory->build(WorkflowTemplate::StandardBilling);
        $validator = app(WorkflowDefinitionValidator::class);

        $validator->validate($definition);

        $this->assertSame(2, $definition['meta']['block_editor_version'] ?? null);
        $this->assertArrayHasKey('block_graph', $definition['meta']);
        $submit = collect($definition['transitions'])->first(
            fn (array $t) => ($t['trigger'] ?? '') === 'execution_submitted',
        );
        $this->assertNotEmpty($submit['notify']['enabled'] ?? false);
    }
}
