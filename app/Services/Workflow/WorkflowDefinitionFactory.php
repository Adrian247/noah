<?php

namespace App\Services\Workflow;

use App\Enums\WorkflowTemplate;
use Illuminate\Support\Arr;

class WorkflowDefinitionFactory
{
    public const META_KEY = 'meta';

    /**
     * @return list<array{key: string, label: string, description: string, default_options: array<string, bool>}>
     */
    public function catalog(): array
    {
        return array_map(
            fn (WorkflowTemplate $t) => [
                'key' => $t->value,
                'label' => $t->label(),
                'description' => $t->description(),
                'default_options' => $t->defaultOptions(),
            ],
            WorkflowTemplate::cases(),
        );
    }

    /**
     * @param  array<string, bool>  $options
     * @return array<string, mixed>
     */
    public function build(WorkflowTemplate $template, array $options = []): array
    {
        $options = array_merge($template->defaultOptions(), $options);

        return WorkflowRuntime::withDefaultLayout(
            $this->buildDefinition($template, $options),
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, bool>  $options
     * @return array<string, mixed>
     */
    public function applyOptions(array $definition, array $options): array
    {
        $meta = $definition[self::META_KEY] ?? [];
        $templateKey = $meta['template'] ?? WorkflowTemplate::StandardBilling->value;
        $template = WorkflowTemplate::tryFrom((string) $templateKey) ?? WorkflowTemplate::StandardBilling;
        $mergedOptions = array_merge($template->defaultOptions(), $meta['options'] ?? [], $options);

        if ($template === WorkflowTemplate::StandardBilling) {
            $layout = $definition['layout'] ?? ['nodes' => []];
            $graph = $meta['block_graph'] ?? WorkflowBlockCompiler::defaultGraph();
            if (is_array($graph['nodes'] ?? null) && is_array($layout['nodes'] ?? null)) {
                foreach ($graph['nodes'] as $index => $node) {
                    if (! is_array($node)) {
                        continue;
                    }
                    $nodeId = (string) ($node['id'] ?? '');
                    if ($nodeId !== '' && isset($layout['nodes'][$nodeId]) && is_array($layout['nodes'][$nodeId])) {
                        $graph['nodes'][$index]['position'] = $layout['nodes'][$nodeId];
                    }
                }
            }

            $base = array_merge($definition, [
                'layout' => $layout,
                self::META_KEY => [
                    'template' => $template->value,
                    'options' => $mergedOptions,
                ],
            ]);

            return WorkflowRuntime::withDefaultLayout(
                app(WorkflowBlockCompiler::class)->compile($graph, $base),
            );
        }

        $layout = $definition['layout'] ?? ['nodes' => []];
        $built = $this->buildDefinition($template, $mergedOptions);
        $built['layout'] = $layout;
        $built[self::META_KEY] = [
            'template' => $template->value,
            'options' => $mergedOptions,
        ];

        return WorkflowRuntime::withDefaultLayout($built);
    }

    /**
     * @param  array<string, bool>  $options
     * @return array<string, mixed>
     */
    private function buildDefinition(WorkflowTemplate $template, array $options): array
    {
        $mergedOptions = array_merge($template->defaultOptions(), $options);

        if ($template === WorkflowTemplate::StandardBilling) {
            $base = [
                self::META_KEY => [
                    'template' => $template->value,
                    'options' => $mergedOptions,
                ],
            ];

            return WorkflowRuntime::withDefaultLayout(
                app(WorkflowBlockCompiler::class)->compile(WorkflowBlockCompiler::defaultGraph(), $base),
            );
        }

        $includeBilling = (bool) ($mergedOptions['include_billing'] ?? true);
        $dualReview = (bool) ($mergedOptions['dual_review'] ?? false);
        $routineValidated = (bool) ($mergedOptions['routine_validated_on_approve'] ?? true);
        $includeEmail = (bool) ($mergedOptions['include_email_step'] ?? false);

        if ($template === WorkflowTemplate::DualReview) {
            $dualReview = true;
        }
        if ($template === WorkflowTemplate::ClassicNoBilling || $template === WorkflowTemplate::ValidationOnly) {
            $includeBilling = false;
        }
        if ($template === WorkflowTemplate::ValidationOnly) {
            $routineValidated = false;
        }

        $options = [
            'include_billing' => $includeBilling,
            'dual_review' => $dualReview,
            'routine_validated_on_approve' => $routineValidated,
            'include_email_step' => $includeEmail,
        ];

        $steps = [
            'field_execution' => [
                'type' => 'human_task',
                'label' => 'Ejecución en campo',
                'assigned_role' => 'technician',
            ],
            'supervisor_review' => [
                'type' => 'human_task',
                'label' => 'Revisión supervisor',
                'assigned_role' => 'supervisor',
            ],
        ];

        if ($dualReview) {
            $steps['lead_review'] = [
                'type' => 'human_task',
                'label' => 'Revisión jefe de taller',
                'assigned_role' => 'supervisor',
            ];
        }

        if ($includeBilling) {
            $steps[WorkflowRuntime::STEP_BILLING] = [
                'type' => 'human_task',
                'label' => 'Facturación',
                'assigned_role' => 'billing',
            ];
        }

        $steps['complete'] = [
            'type' => 'end',
            'label' => 'Cierre',
        ];

        $transitions = [];

        $firstTarget = $includeEmail ? 'email_notify_supervisors' : 'supervisor_review';
        $transitions[] = [
            'from' => 'field_execution',
            'to' => $firstTarget,
            'trigger' => 'execution_submitted',
        ];

        if ($includeEmail) {
            $steps['email_notify_supervisors'] = [
                'type' => 'service_task',
                'label' => 'Notificar por email',
                'task' => 'send_email',
                'email' => [
                    'roles' => ['supervisor', 'administrator'],
                    'template' => 'routine_pending_validation',
                    'subject' => 'Noah — Rutina pendiente de validación',
                    'message' => 'Un técnico envió una rutina para tu revisión.',
                ],
            ];
            $transitions[] = [
                'from' => 'email_notify_supervisors',
                'to' => 'supervisor_review',
                'trigger' => WorkflowRuntime::TRIGGER_SERVICE_COMPLETE,
            ];
        }

        $transitions[] = [
            'from' => 'supervisor_review',
            'to' => 'field_execution',
            'trigger' => 'rejected',
        ];

        $firstApprovalTarget = $dualReview ? 'lead_review' : ($includeBilling ? WorkflowRuntime::STEP_BILLING : 'complete');
        $firstApproval = [
            'from' => 'supervisor_review',
            'to' => $firstApprovalTarget,
            'trigger' => 'approved',
        ];
        if ($routineValidated && ! $dualReview && ! $includeBilling) {
            $firstApproval['actions'] = ['routine_validated'];
        }
        if ($routineValidated && ! $dualReview && $includeBilling) {
            $firstApproval['actions'] = ['routine_validated'];
        }
        $transitions[] = $firstApproval;

        if ($dualReview) {
            $transitions[] = [
                'from' => 'lead_review',
                'to' => 'field_execution',
                'trigger' => 'rejected',
            ];
            $leadTarget = $includeBilling ? WorkflowRuntime::STEP_BILLING : 'complete';
            $leadApproval = [
                'from' => 'lead_review',
                'to' => $leadTarget,
                'trigger' => 'approved',
            ];
            if ($routineValidated) {
                $leadApproval['actions'] = ['routine_validated'];
            }
            $transitions[] = $leadApproval;
        }

        if ($includeBilling) {
            $transitions[] = [
                'from' => WorkflowRuntime::STEP_BILLING,
                'to' => 'complete',
                'trigger' => WorkflowRuntime::TRIGGER_INVOICE_ISSUED,
            ];
        }

        return [
            'initial_step' => 'field_execution',
            'steps' => $steps,
            'transitions' => $transitions,
            self::META_KEY => [
                'template' => $template->value,
                'options' => $options,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $definition
     */
    public function hasBillingStep(?array $definition): bool
    {
        if ($definition === null) {
            return true;
        }

        return array_key_exists(WorkflowRuntime::STEP_BILLING, $definition['steps'] ?? []);
    }

    /**
     * @param  array<string, mixed>|null  $definition
     */
    public function routineValidatedOnLastApproval(?array $definition): bool
    {
        $options = Arr::get($definition, self::META_KEY.'.options', []);

        return (bool) ($options['routine_validated_on_approve'] ?? true);
    }

    /**
     * @param  array<string, mixed>|null  $definition
     */
    public static function hasEmailServiceStep(?array $definition): bool
    {
        if ($definition === null) {
            return false;
        }

        foreach ($definition['steps'] ?? [] as $meta) {
            if (is_array($meta)
                && ($meta['type'] ?? null) === 'service_task'
                && ($meta['task'] ?? null) === 'send_email') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function emailStepBlueprint(): array
    {
        return [
            'type' => 'service_task',
            'label' => 'Enviar email',
            'task' => 'send_email',
            'email' => [
                'roles' => ['supervisor', 'administrator'],
                'template' => 'routine_pending_validation',
                'subject' => 'Noah — Notificación de rutina',
                'message' => 'Hay una rutina que requiere tu atención.',
            ],
        ];
    }
}
