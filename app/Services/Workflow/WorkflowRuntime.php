<?php

namespace App\Services\Workflow;

use App\Enums\RoutineStatus;
use App\Events\ExecutionSubmitted;
use App\Events\RoutineValidated;
use App\Models\Routine;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class WorkflowRuntime
{
    public static function defaultDefinition(): array
    {
        return self::withDefaultLayout([
            'initial_step' => 'field_execution',
            'steps' => [
                'field_execution' => ['type' => 'human_task', 'label' => 'Ejecución en campo'],
                'supervisor_review' => ['type' => 'human_task', 'label' => 'Revisión supervisor'],
                'complete' => ['type' => 'end', 'label' => 'Cierre'],
            ],
            'transitions' => [
                [
                    'from' => 'field_execution',
                    'to' => 'supervisor_review',
                    'trigger' => 'execution_submitted',
                ],
                [
                    'from' => 'supervisor_review',
                    'to' => 'complete',
                    'trigger' => 'approved',
                    'actions' => ['routine_validated'],
                ],
                [
                    'from' => 'supervisor_review',
                    'to' => 'field_execution',
                    'trigger' => 'rejected',
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function withDefaultLayout(array $definition): array
    {
        $defaults = [
            'field_execution' => ['x' => 48, 'y' => 140],
            'supervisor_review' => ['x' => 280, 'y' => 140],
            'complete' => ['x' => 512, 'y' => 140],
        ];

        $layout = $definition['layout'] ?? [];
        $nodes = $layout['nodes'] ?? [];

        foreach ($defaults as $key => $pos) {
            if (! isset($nodes[$key])) {
                $nodes[$key] = $pos;
            }
        }

        $definition['layout'] = ['nodes' => $nodes];

        return $definition;
    }

    public function ensureInstance(Routine $routine): WorkflowInstance
    {
        $existing = WorkflowInstance::query()->where('routine_id', $routine->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $routine->loadMissing('routineType.workflowDefinition');
        $definition = $routine->routineType?->workflowDefinition;

        if ($definition === null) {
            throw new InvalidArgumentException('Routine type has no workflow definition.');
        }

        $initial = Arr::get($definition->definition, 'initial_step', 'field_execution');

        return WorkflowInstance::query()->create([
            'workflow_definition_id' => $definition->id,
            'routine_id' => $routine->id,
            'current_step_key' => $initial,
            'status' => 'active',
        ]);
    }

    public function onExecutionSubmitted(Routine $routine, User $actor): void
    {
        $instance = $this->ensureInstance($routine);
        $this->applyTransition($instance, 'execution_submitted', $actor);
        $routine->update(['status' => RoutineStatus::PendingValidation]);
        ExecutionSubmitted::dispatch($routine->fresh(['asset', 'routineType', 'assignee']));
    }

    public function onApproved(Routine $routine, User $actor): void
    {
        $instance = WorkflowInstance::query()->where('routine_id', $routine->id)->firstOrFail();
        $transition = $this->applyTransition($instance, 'approved', $actor);

        $execution = $routine->latestExecution;
        if ($execution === null) {
            throw new InvalidArgumentException('No execution to validate.');
        }

        $execution->update([
            'validated_at' => now(),
            'validated_by' => $actor->id,
        ]);

        $routine->update(['status' => RoutineStatus::Validated]);

        $actions = Arr::get($transition, 'actions', []);
        if (in_array('routine_validated', $actions, true)) {
            RoutineValidated::dispatch($routine->fresh(), $execution->fresh());
        }
    }

    public function onRejected(Routine $routine, User $actor, string $reason): void
    {
        $instance = WorkflowInstance::query()->where('routine_id', $routine->id)->firstOrFail();
        $this->applyTransition($instance, 'rejected', $actor, ['reason' => $reason]);
        $routine->update(['status' => RoutineStatus::Assigned]);
    }

  /**
     * @return array<string, mixed>|null
     */
    private function applyTransition(
        WorkflowInstance $instance,
        string $trigger,
        User $actor,
        array $metadata = [],
    ): ?array {
        $instance->loadMissing('definition');
        $definition = $instance->definition->definition;
        $from = $instance->current_step_key;

        $transition = collect($definition['transitions'] ?? [])
            ->first(fn (array $t) => ($t['from'] ?? null) === $from && ($t['trigger'] ?? null) === $trigger);

        if ($transition === null) {
            throw new InvalidArgumentException("Transition {$trigger} not allowed from {$from}.");
        }

        $to = $transition['to'];
        WorkflowTransition::query()->create([
            'workflow_instance_id' => $instance->id,
            'from_step' => $from,
            'to_step' => $to,
            'trigger' => $trigger,
            'actor_user_id' => $actor->id,
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);

        $status = $to === 'complete' ? 'completed' : 'active';
        $instance->update([
            'current_step_key' => $to,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        return $transition;
    }

    public function seedDefinitionForCompany(int $companyId): WorkflowDefinition
    {
        return WorkflowDefinition::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'slug' => 'routine-validation-v1',
                'version' => 1,
            ],
            [
                'name' => 'Validación estándar de rutina',
                'status' => 'published',
                'definition' => self::defaultDefinition(),
            ]
        );
    }
}
