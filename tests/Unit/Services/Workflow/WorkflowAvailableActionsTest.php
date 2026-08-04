<?php

namespace Tests\Unit\Services\Workflow;

use App\Models\Company;
use App\Models\User;
use App\Services\Routines\DemoRoutineFactory;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowAvailableActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_actions_use_custom_transition_labels(): void
    {
        $this->seed();
        $company = Company::query()->first();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->first();
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $routine->load('workflowInstance.definition');
        $instance = $routine->workflowInstance;
        $this->assertNotNull($instance);

        $instance->update(['current_step_key' => 'supervisor_review']);

        $actions = app(WorkflowRuntime::class)->availableActions($instance->fresh('definition'));

        $byTrigger = collect($actions)->keyBy('trigger');
        $this->assertSame('Servicio a facturar', $byTrigger['approved']['label'] ?? '');
        $this->assertSame('Rechazo', $byTrigger['rejected']['label'] ?? '');
    }
}
