<?php

namespace App\Services\Workflow;

use Illuminate\Validation\ValidationException;

/**
 * Compila meta.block_graph → steps + transitions del motor.
 */
class WorkflowBlockCompiler
{
    public const ROUTINE_STEP = 'field_execution';

    public const END_STEP = 'complete';

    public const BILLING_STEP = 'billing_review';

    public const SUPERVISOR_STEP = 'supervisor_review';

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function defaultGraph(): array
    {
        return [
            'nodes' => [
                [
                    'id' => self::ROUTINE_STEP,
                    'kind' => 'routine',
                    'label' => 'Servicio',
                    'position' => ['x' => 72, 'y' => 240],
                    'locked' => true,
                    'assignment_notify' => [
                        'enabled' => true,
                        'subject' => 'Nuevo servicio asignado #{routine.id}',
                        'body_html' => '<p>Hola {user.name}, se te asignó el servicio {routine.id} ({routine_type.name}) en el activo {asset.tag}.</p><p>Entra a Phoenix para revisarlo y ejecutarlo.</p>',
                        'recipients' => ['executing_technician'],
                    ],
                ],
                [
                    'id' => self::SUPERVISOR_STEP,
                    'kind' => 'role',
                    'label' => 'Supervisor',
                    'assigned_role' => 'supervisor',
                    'position' => ['x' => 360, 'y' => 96],
                ],
                [
                    'id' => self::BILLING_STEP,
                    'kind' => 'role',
                    'label' => 'Facturación',
                    'assigned_role' => 'billing',
                    'position' => ['x' => 600, 'y' => 96],
                ],
                [
                    'id' => self::END_STEP,
                    'kind' => 'end',
                    'label' => 'Fin',
                    'position' => ['x' => 840, 'y' => 96],
                ],
            ],
            'edges' => [
                [
                    'id' => 'e_revision',
                    'source' => self::ROUTINE_STEP,
                    'target' => self::SUPERVISOR_STEP,
                    'action' => 'submit',
                    'label' => 'Revisión',
                    'notify' => [
                        'enabled' => true,
                        'subject' => 'Ejecuta servicio {routine.id}',
                        'body_html' => '<p>Hola {user.name}, registramos tu ejecución del servicio {routine.id} ({routine_type.name}) en el activo {asset.tag}.</p>',
                        'recipients' => ['executing_technician'],
                    ],
                ],
                [
                    'id' => 'e_reject',
                    'source' => self::SUPERVISOR_STEP,
                    'target' => self::ROUTINE_STEP,
                    'action' => 'reject',
                    'label' => 'Rechazo',
                    'notify' => [
                        'enabled' => true,
                        'subject' => 'Servicio {routine.id} rechazado',
                        'body_html' => '<p>Hola {user.name}, el servicio {routine.id} fue rechazado y debe volver a ejecutarse en campo.</p>',
                        'recipients' => ['executing_technician'],
                    ],
                ],
                [
                    'id' => 'e_to_billing',
                    'source' => self::SUPERVISOR_STEP,
                    'target' => self::BILLING_STEP,
                    'action' => 'approve',
                    'label' => 'Servicio a facturar',
                    'routine_validated' => true,
                ],
                [
                    'id' => 'e_invoice',
                    'source' => self::BILLING_STEP,
                    'target' => self::END_STEP,
                    'action' => 'invoice',
                    'label' => 'Emisión de factura',
                    'notify' => [
                        'enabled' => true,
                        'subject' => 'Cierre de servicio {routine.id}',
                        'body_html' => '<p>Hola {user.name}, le informamos que el servicio {routine.id} del cliente {client.name} ha sido finalizado, a continuación el detalle del servicio realizado:</p><p>{routine.tasks_detail}</p>',
                        'recipients' => ['incident_creator', 'executing_technician', 'approval_supervisor'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $graph
     * @param  array<string, mixed>  $baseDefinition
     * @return array<string, mixed>
     */
    public function compile(array $graph, array $baseDefinition = []): array
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        if (! is_array($nodes) || ! is_array($edges)) {
            throw ValidationException::withMessages([
                'definition.meta.block_graph' => 'Grafo de bloques inválido.',
            ]);
        }

        $this->validateGraph($nodes, $edges);

        $steps = [];
        $layout = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $id = (string) ($node['id'] ?? '');
            $kind = (string) ($node['kind'] ?? '');
            if ($id === '' || $kind === '') {
                continue;
            }
            if (in_array($kind, ['stage', 'email'], true)) {
                continue;
            }
            if ($kind === 'billing') {
                $kind = 'role';
                $node['assigned_role'] = $node['assigned_role'] ?? 'billing';
            }

            $pos = $node['position'] ?? ['x' => 0, 'y' => 0];
            $layout[$id] = [
                'x' => (float) ($pos['x'] ?? 0),
                'y' => (float) ($pos['y'] ?? 0),
            ];

            $steps[$id] = $this->stepFromNode($node, $kind);
        }

        if (! isset($steps[self::ROUTINE_STEP])) {
            throw ValidationException::withMessages([
                'definition.meta.block_graph' => 'Debe existir el bloque Servicio.',
            ]);
        }

        $transitions = [];
        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $action = (string) ($edge['action'] ?? '');
            if ($action === 'handoff' || $action === 'email_done') {
                continue;
            }
            $transitions[] = $this->transitionFromEdge($edge);
        }

        $meta = is_array($baseDefinition['meta'] ?? null) ? $baseDefinition['meta'] : [];
        $meta['block_editor_version'] = 2;
        $meta['block_graph'] = ['nodes' => array_values($nodes), 'edges' => array_values($edges)];

        return [
            'initial_step' => self::ROUTINE_STEP,
            'steps' => $steps,
            'transitions' => $transitions,
            'layout' => ['nodes' => $layout],
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function decompile(array $definition): array
    {
        $stored = $definition['meta']['block_graph'] ?? null;
        if (is_array($stored) && isset($stored['nodes'], $stored['edges'])) {
            if (! $this->needsBlockGraphUpgrade($definition)) {
                return [
                    'nodes' => array_values($stored['nodes']),
                    'edges' => array_values($stored['edges']),
                ];
            }
        }

        return $this->inferGraphFromDefinition($definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function ensureEditorDefinition(array $definition): array
    {
        if (! $this->needsBlockGraphUpgrade($definition)) {
            return $definition;
        }

        $graph = self::defaultGraph();
        $this->hydrateGraphFromTransitions($graph, $definition['transitions'] ?? []);

        return $this->compile($graph, $definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function needsBlockGraphUpgrade(array $definition): bool
    {
        $version = (int) ($definition['meta']['block_editor_version'] ?? 1);
        $stored = $definition['meta']['block_graph'] ?? null;
        if ($version < 2 || ! is_array($stored) || ! isset($stored['nodes'], $stored['edges'])) {
            return true;
        }

        return $this->isLegacyGraph($stored);
    }

    /**
     * @param  array<string, mixed>  $graph
     */
    private function isLegacyGraph(array $graph): bool
    {
        foreach ($graph['nodes'] ?? [] as $node) {
            if (! is_array($node)) {
                continue;
            }
            $kind = (string) ($node['kind'] ?? '');
            if (in_array($kind, ['stage', 'email', 'billing'], true)) {
                return true;
            }
        }
        $nodeIds = [];
        foreach ($graph['nodes'] ?? [] as $node) {
            if (is_array($node) && isset($node['id'])) {
                $nodeIds[(string) $node['id']] = true;
            }
        }
        foreach ($graph['edges'] ?? [] as $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $action = (string) ($edge['action'] ?? '');
            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');
            if ($action === 'handoff' || $source === 'stage_review' || $target === 'stage_review') {
                return true;
            }
            if (! isset($nodeIds[$source], $nodeIds[$target])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $graph
     * @param  list<array<string, mixed>>  $transitions
     */
    private function hydrateGraphFromTransitions(array &$graph, array $transitions): void
    {
        foreach ($graph['edges'] as $index => $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $action = (string) ($edge['action'] ?? '');
            $trigger = match ($action) {
                'submit' => 'execution_submitted',
                'reject' => 'rejected',
                'invoice' => 'invoice_issued',
                default => 'approved',
            };
            foreach ($transitions as $transition) {
                if (! is_array($transition)) {
                    continue;
                }
                if (($transition['from'] ?? '') !== ($edge['source'] ?? '')
                    || ($transition['trigger'] ?? '') !== $trigger) {
                    continue;
                }
                if (! empty($transition['label'])) {
                    $graph['edges'][$index]['label'] = (string) $transition['label'];
                }
                if (! empty($transition['notify']) && is_array($transition['notify'])) {
                    $graph['edges'][$index]['notify'] = $transition['notify'];
                }
                if ($action === 'approve'
                    && is_array($transition['actions'] ?? null)
                    && in_array('routine_validated', $transition['actions'], true)) {
                    $graph['edges'][$index]['routine_validated'] = true;
                }
                break;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     */
    private function validateGraph(array $nodes, array $edges): void
    {
        $byId = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $id = (string) ($node['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $node;
            }
        }

        if (! isset($byId[self::ROUTINE_STEP])) {
            throw ValidationException::withMessages([
                'definition.meta.block_graph' => 'Falta el bloque fijo Servicio.',
            ]);
        }

        foreach ($edges as $index => $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $action = (string) ($edge['action'] ?? '');
            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');

            if (! isset($byId[$source], $byId[$target])) {
                throw ValidationException::withMessages([
                    "definition.meta.block_graph.edges.{$index}" => 'Origen o destino inexistente.',
                ]);
            }

            $sourceKind = (string) ($byId[$source]['kind'] ?? '');
            if ($sourceKind === 'billing') {
                $sourceKind = 'role';
            }

            if ($action === 'reject') {
                if ($sourceKind !== 'role' || $target !== self::ROUTINE_STEP) {
                    throw ValidationException::withMessages([
                        "definition.meta.block_graph.edges.{$index}" => 'Rechazo solo desde un Rol hacia Servicio.',
                    ]);
                }
            }

            if ($action === 'approve') {
                if ($sourceKind !== 'role') {
                    throw ValidationException::withMessages([
                        "definition.meta.block_graph.edges.{$index}" => 'Aprobar solo desde un Rol.',
                    ]);
                }
                if ($target === self::ROUTINE_STEP) {
                    throw ValidationException::withMessages([
                        "definition.meta.block_graph.edges.{$index}" => 'Aprobar no puede apuntar a Servicio.',
                    ]);
                }
            }

            if ($action === 'submit' && $source !== self::ROUTINE_STEP) {
                throw ValidationException::withMessages([
                    "definition.meta.block_graph.edges.{$index}" => 'Enviar solo desde Servicio.',
                ]);
            }

            if ($action === 'invoice') {
                $targetKind = (string) ($byId[$target]['kind'] ?? '');
                if ($sourceKind !== 'role' || $targetKind !== 'end') {
                    throw ValidationException::withMessages([
                        "definition.meta.block_graph.edges.{$index}" => 'Emisión de factura solo desde Rol hacia Fin.',
                    ]);
                }
            }
        }

        $submitCount = 0;
        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }
            if (($edge['action'] ?? '') === 'submit' && ($edge['source'] ?? '') === self::ROUTINE_STEP) {
                $submitCount++;
            }
        }
        if ($submitCount > 1) {
            throw ValidationException::withMessages([
                'definition.meta.block_graph' => 'Servicio solo puede tener una salida de envío.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function stepFromNode(array $node, string $kind): array
    {
        $label = (string) ($node['label'] ?? $node['id'] ?? 'Paso');

        $step = match ($kind) {
            'routine' => [
                'type' => 'human_task',
                'label' => $label,
                'assigned_role' => 'technician',
            ],
            'role' => [
                'type' => 'human_task',
                'label' => $label,
                'assigned_role' => (string) ($node['assigned_role'] ?? 'supervisor'),
            ],
            'end' => [
                'type' => 'end',
                'label' => $label,
            ],
            default => throw ValidationException::withMessages([
                'definition.meta.block_graph' => "Tipo de bloque no soportado: {$kind}",
            ]),
        };

        if ($kind === 'routine'
            && ! empty($node['assignment_notify'])
            && is_array($node['assignment_notify'])
            && ! empty($node['assignment_notify']['enabled'])) {
            $step['assignment_notify'] = $node['assignment_notify'];
        }

        return $step;
    }

    /**
     * @param  array<string, mixed>  $edge
     * @return array<string, mixed>
     */
    private function transitionFromEdge(array $edge): array
    {
        $action = (string) ($edge['action'] ?? 'approve');

        $trigger = match ($action) {
            'submit' => 'execution_submitted',
            'approve' => 'approved',
            'reject' => 'rejected',
            'invoice' => 'invoice_issued',
            default => throw ValidationException::withMessages([
                'definition.meta.block_graph' => "Acción de arista no soportada: {$action}",
            ]),
        };

        $transition = [
            'from' => (string) ($edge['source'] ?? ''),
            'to' => (string) ($edge['target'] ?? ''),
            'trigger' => $trigger,
            'label' => (string) ($edge['label'] ?? ''),
        ];

        if ($action === 'approve' && ! empty($edge['routine_validated'])) {
            $transition['actions'] = ['routine_validated'];
        }

        if (! empty($edge['notify']) && is_array($edge['notify']) && ! empty($edge['notify']['enabled'])) {
            $transition['notify'] = $edge['notify'];
        }

        return $transition;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function inferGraphFromDefinition(array $definition): array
    {
        $steps = is_array($definition['steps'] ?? null) ? $definition['steps'] : [];
        $layout = $definition['layout']['nodes'] ?? [];
        $nodes = [];

        foreach ($steps as $id => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $kind = $this->inferKind($id, $meta);
            if ($kind === 'email') {
                continue;
            }
            $node = [
                'id' => $id,
                'kind' => $kind === 'billing' ? 'role' : $kind,
                'label' => (string) ($meta['label'] ?? $id),
                'position' => $layout[$id] ?? ['x' => 80, 'y' => 120],
            ];
            if ($kind === 'role' || $kind === 'billing') {
                $node['assigned_role'] = (string) ($meta['assigned_role'] ?? 'supervisor');
            }
            if ($kind === 'routine' && is_array($meta['assignment_notify'] ?? null)) {
                $node['assignment_notify'] = $meta['assignment_notify'];
            }
            $nodes[] = $node;
        }

        $edges = [];
        foreach ($definition['transitions'] ?? [] as $index => $t) {
            if (! is_array($t)) {
                continue;
            }
            $trigger = (string) ($t['trigger'] ?? '');
            $action = match ($trigger) {
                'execution_submitted' => 'submit',
                'approved' => 'approve',
                'rejected' => 'reject',
                'invoice_issued' => 'invoice',
                default => 'approve',
            };
            $edge = [
                'id' => 'e-'.$index,
                'source' => (string) ($t['from'] ?? ''),
                'target' => (string) ($t['to'] ?? ''),
                'action' => $action,
                'label' => (string) ($t['label'] ?? ''),
            ];
            if ($action === 'approve' && is_array($t['actions'] ?? null) && in_array('routine_validated', $t['actions'], true)) {
                $edge['routine_validated'] = true;
            }
            if (is_array($t['notify'] ?? null)) {
                $edge['notify'] = $t['notify'];
            }
            $edges[] = $edge;
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function inferKind(string $id, array $meta): string
    {
        if ($id === self::ROUTINE_STEP) {
            return 'routine';
        }
        if ($id === self::END_STEP) {
            return 'end';
        }
        if ($id === self::BILLING_STEP || ($meta['assigned_role'] ?? null) === 'billing') {
            return 'billing';
        }
        if (($meta['type'] ?? null) === 'service_task') {
            return 'email';
        }
        if (($meta['type'] ?? null) === 'end') {
            return 'end';
        }

        return 'role';
    }
}
