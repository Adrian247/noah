<?php

namespace App\Services\Workflow;

use App\Enums\RoutineStatus;
use App\Events\ExecutionSubmitted;
use App\Events\RoutineValidated;
use App\Models\Routine;
use App\Models\RoutineType;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditLogger;
use App\Support\AuditCorrelation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorkflowRuntime
{
    public const STEP_BILLING = 'billing_review';

    public const TRIGGER_INVOICE_ISSUED = 'invoice_issued';

    public const TRIGGER_SERVICE_COMPLETE = 'service_complete';

    public static function defaultDefinition(): array
    {
        return app(WorkflowDefinitionFactory::class)->build(
            \App\Enums\WorkflowTemplate::StandardBilling,
        );
    }

    /**
     * @param  array<string, mixed>|null  $definition
     */
    public static function definitionHasBillingStep(?array $definition): bool
    {
        return app(WorkflowDefinitionFactory::class)->hasBillingStep($definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function withDefaultLayout(array $definition): array
    {
        $defaults = [
            'field_execution' => ['x' => 72, 'y' => 240],
            'supervisor_review' => ['x' => 360, 'y' => 96],
            self::STEP_BILLING => ['x' => 600, 'y' => 96],
            'complete' => ['x' => 840, 'y' => 96],
            'email_notify_supervisors' => ['x' => 200, 'y' => 140],
            'lead_review' => ['x' => 400, 'y' => 60],
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
        $correlationId = (string) Str::uuid();

        $instance = WorkflowInstance::query()->create([
            'workflow_definition_id' => $definition->id,
            'routine_id' => $routine->id,
            'correlation_id' => $correlationId,
            'current_step_key' => $initial,
            'status' => 'active',
        ]);

        AuditCorrelation::set($correlationId);

        $this->notifyAssignmentIfConfigured($routine, $definition->definition ?? []);

        return $instance;
    }

    /**
     * @param  array<string, mixed>  $definitionPayload
     */
    private function notifyAssignmentIfConfigured(Routine $routine, array $definitionPayload): void
    {
        $routine->loadMissing(['assignee', 'creator']);
        if ($routine->assigned_to === null || $routine->assignee === null) {
            return;
        }

        $notify = Arr::get($definitionPayload, 'steps.'.WorkflowBlockCompiler::ROUTINE_STEP.'.assignment_notify');
        if (! is_array($notify) || empty($notify['enabled'])) {
            $graphNode = collect(Arr::get($definitionPayload, 'meta.block_graph.nodes', []))
                ->first(fn ($node) => is_array($node) && ($node['id'] ?? null) === WorkflowBlockCompiler::ROUTINE_STEP);
            $notify = is_array($graphNode['assignment_notify'] ?? null) ? $graphNode['assignment_notify'] : null;
        }

        if (! is_array($notify) || empty($notify['enabled'])) {
            return;
        }

        $actor = $routine->creator ?? $routine->assignee;
        if ($actor === null) {
            return;
        }

        app(WorkflowStepEmailNotifier::class)->sendForTransitionNotify($routine, $notify, $actor);
    }

    public function onExecutionSubmitted(Routine $routine, User $actor, ?AuditLogger $audit = null): void
    {
        $instance = $this->ensureInstance($routine);
        $this->withCorrelation($instance, fn () => $this->applyTransition($instance, 'execution_submitted', $actor, [], $audit));
        $routine->update(['status' => RoutineStatus::PendingValidation]);
        ExecutionSubmitted::dispatch($routine->fresh(['asset', 'routineType', 'assignee']));
    }

    public function onApproved(Routine $routine, User $actor, ?AuditLogger $audit = null): void
    {
        $instance = WorkflowInstance::query()->where('routine_id', $routine->id)->firstOrFail();

        $transition = $this->withCorrelation($instance, fn () => $this->applyTransition($instance, 'approved', $actor, [], $audit));

        $execution = $routine->latestExecution;
        if ($execution === null) {
            throw new InvalidArgumentException('No execution to validate.');
        }

        $execution->update([
            'validated_at' => now(),
            'validated_by' => $actor->id,
        ]);

        $definition = $instance->definition->definition ?? [];
        $toStep = $transition['to'] ?? '';
        $toMeta = $definition['steps'][$toStep] ?? [];
        $isBillingStep = $toStep === self::STEP_BILLING
            || (($toMeta['assigned_role'] ?? null) === 'billing');
        $isEnd = ($toMeta['type'] ?? null) === 'end' || $toStep === 'complete';

        if ($isBillingStep) {
            $routine->update(['status' => RoutineStatus::PendingBilling]);
        } elseif ($isEnd) {
            $routine->update(['status' => RoutineStatus::Validated]);
        } else {
            $routine->update(['status' => RoutineStatus::PendingValidation]);
        }

        $actions = Arr::get($transition, 'actions', []);
        if (in_array('routine_validated', $actions, true)) {
            RoutineValidated::dispatch($routine->fresh(), $execution->fresh());
        }
    }

    public function onRejected(Routine $routine, User $actor, string $reason, ?AuditLogger $audit = null): void
    {
        $instance = WorkflowInstance::query()->where('routine_id', $routine->id)->firstOrFail();
        $this->withCorrelation($instance, fn () => $this->applyTransition($instance, 'rejected', $actor, ['reason' => $reason], $audit));
        $routine->update(['status' => RoutineStatus::Assigned]);
    }

    public function onInvoiceIssued(Routine $routine, User $actor, ?AuditLogger $audit = null): void
    {
        $instance = WorkflowInstance::query()->where('routine_id', $routine->id)->firstOrFail();
        $definition = $instance->definition->definition ?? [];
        if (! $this->canApplyTrigger($instance, self::TRIGGER_INVOICE_ISSUED, $definition)) {
            $routine->update(['status' => RoutineStatus::Invoiced]);

            return;
        }
        $this->withCorrelation($instance, fn () => $this->applyTransition($instance, self::TRIGGER_INVOICE_ISSUED, $actor, [], $audit));
        $routine->update(['status' => RoutineStatus::Invoiced]);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function canApplyTrigger(WorkflowInstance $instance, string $trigger, ?array $definition = null): bool
    {
        $definition ??= $instance->definition->definition ?? [];
        $from = $instance->current_step_key;

        return collect($definition['transitions'] ?? [])
            ->contains(fn (array $t) => ($t['from'] ?? null) === $from && ($t['trigger'] ?? null) === $trigger);
    }

    /**
     * Acciones salientes del paso actual (para etiquetas en UI).
     *
     * @return list<array{trigger: string, label: string, to_step: string}>
     */
    public function availableActions(WorkflowInstance $instance): array
    {
        $instance->loadMissing('definition');
        $definition = $instance->definition?->definition ?? [];
        $from = $instance->current_step_key;
        $actions = [];

        foreach ($definition['transitions'] ?? [] as $transition) {
            if (! is_array($transition)) {
                continue;
            }
            if (($transition['from'] ?? null) !== $from) {
                continue;
            }
            $trigger = (string) ($transition['trigger'] ?? '');
            if (! in_array($trigger, ['execution_submitted', 'approved', 'rejected', 'invoice_issued'], true)) {
                continue;
            }
            $actions[] = [
                'trigger' => $trigger,
                'label' => $this->transitionLabel($transition, $trigger),
                'to_step' => (string) ($transition['to'] ?? ''),
            ];
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $transition
     */
    public function transitionLabel(array $transition, string $trigger): string
    {
        $custom = trim((string) ($transition['label'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        return match ($trigger) {
            'execution_submitted' => 'Enviar ejecución',
            'approved' => 'Aprobar',
            'rejected' => 'Rechazar',
            'invoice_issued' => 'Emitir factura',
            default => $trigger,
        };
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withCorrelation(WorkflowInstance $instance, callable $callback)
    {
        $previous = AuditCorrelation::get();
        AuditCorrelation::set($instance->correlation_id);
        try {
            return $callback();
        } finally {
            AuditCorrelation::set($previous);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function applyTransition(
        WorkflowInstance $instance,
        string $trigger,
        User $actor,
        array $metadata = [],
        ?AuditLogger $audit = null,
        int $sideEffectDepth = 0,
    ): ?array {
        $instance->loadMissing('definition', 'routine');
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

        $toStepMeta = $definition['steps'][$to] ?? [];
        $isEnd = $to === 'complete' || (($toStepMeta['type'] ?? null) === 'end');
        $status = $isEnd ? 'completed' : 'active';
        $instance->update([
            'current_step_key' => $to,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        $audit?->record(
            $instance->routine?->company_id,
            $actor->id,
            'workflow.transitioned',
            WorkflowInstance::class,
            $instance->id,
            [
                'trigger' => $trigger,
                'from' => $from,
                'to' => $to,
                'routine_id' => $instance->routine_id,
            ],
            null,
            AuditCorrelation::get(),
        );

        $routine = $instance->routine;
        if ($routine !== null && ! empty($transition['notify']) && is_array($transition['notify'])) {
            app(WorkflowStepEmailNotifier::class)->sendForTransitionNotify($routine, $transition['notify'], $actor);
        }

        $this->runStepSideEffects($instance, $to, $definition, $actor, $audit, $sideEffectDepth);

        return $transition;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function runStepSideEffects(
        WorkflowInstance $instance,
        string $stepKey,
        array $definition,
        User $actor,
        ?AuditLogger $audit,
        int $depth,
    ): void {
        if ($depth > 5) {
            return;
        }

        $instance->refresh();
        $routine = $instance->routine;
        if ($routine === null) {
            return;
        }

        $meta = $definition['steps'][$stepKey] ?? [];

        if (! empty($meta['email_on_enter']) && is_array($meta['email_on_enter'])) {
            app(WorkflowStepEmailNotifier::class)->sendFromConfig($routine, $meta['email_on_enter']);
        }

        if (($meta['type'] ?? null) === 'service_task' && ($meta['task'] ?? null) === 'send_email') {
            app(WorkflowStepEmailNotifier::class)->sendForServiceTask($routine, $meta);

            $next = collect($definition['transitions'] ?? [])
                ->first(fn (array $t) => ($t['from'] ?? '') === $stepKey
                    && ($t['trigger'] ?? null) === self::TRIGGER_SERVICE_COMPLETE);

            if ($next !== null) {
                $this->applyTransition(
                    $instance,
                    self::TRIGGER_SERVICE_COMPLETE,
                    $actor,
                    ['auto' => true],
                    $audit,
                    $depth + 1,
                );
            }
        }
    }

    public function seedDefinitionForCompany(int $companyId): WorkflowDefinition
    {
        $definition = self::defaultDefinition();

        return WorkflowDefinition::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'slug' => 'routine-validation-v1',
                'version' => 1,
            ],
            [
                'name' => 'Validación estándar de rutina',
                'status' => 'published',
                'definition' => $definition,
            ]
        );
    }

    public function assignStandardWorkflowToRoutineTypes(int $companyId, ?int $workflowDefinitionId = null): void
    {
        $workflowId = $workflowDefinitionId ?? $this->seedDefinitionForCompany($companyId)->id;

        RoutineType::query()
            ->where('company_id', $companyId)
            ->update(['workflow_definition_id' => $workflowId]);
    }

    public function syncStandardWorkflowForCompany(int $companyId): WorkflowDefinition
    {
        $workflow = $this->seedDefinitionForCompany($companyId);
        $this->assignStandardWorkflowToRoutineTypes($companyId, $workflow->id);

        return $workflow;
    }
}
