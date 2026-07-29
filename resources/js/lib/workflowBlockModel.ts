import type { WorkflowDefinition } from '@/lib/workflowFlowMapper';

/** Copia profunda solo datos JSON (evita fallos de structuredClone con proxies de Vue). */
export function cloneJson<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}

export const ROUTINE_ID = 'field_execution';
export const END_ID = 'complete';
export const SUPERVISOR_STEP_ID = 'supervisor_review';
export const BILLING_ID = 'billing_review';

export const STAGE_REVIEW_ID = 'stage_review';

/** Disposición acordada en el lienzo (Rutina abajo, flujo principal arriba, rechazo en arco). */
export const STANDARD_WORKFLOW_LAYOUT: Record<string, { x: number; y: number }> = {
    [ROUTINE_ID]: { x: 48, y: 260 },
    [SUPERVISOR_STEP_ID]: { x: 380, y: 72 },
    [BILLING_ID]: { x: 620, y: 72 },
    [END_ID]: { x: 860, y: 72 },
};

export type BlockKind = 'routine' | 'role' | 'end';

export type BlockAction = 'submit' | 'approve' | 'reject' | 'invoice';

export type ActionEmailRecipientKey =
    | 'executing_technician'
    | 'incident_creator'
    | 'approval_supervisor'
    | 'roles';

export type ActionEmailConfig = {
    enabled: boolean;
    subject: string;
    body_html: string;
    recipients: ActionEmailRecipientKey[];
    roles?: string[];
};

export type BlockNode = {
    id: string;
    kind: BlockKind;
    label: string;
    position: { x: number; y: number };
    assigned_role?: string;
    locked?: boolean;
    assignment_notify?: ActionEmailConfig;
};

export type BlockEdge = {
    id: string;
    source: string;
    target: string;
    action: BlockAction;
    /** Nombre visible de la acción (botones y lienzo). */
    label: string;
    routine_validated?: boolean;
    notify?: ActionEmailConfig;
};

export type BlockGraph = {
    nodes: BlockNode[];
    edges: BlockEdge[];
};

export const WORKFLOW_ROLE_CATALOG = [
    { value: 'supervisor', label: 'Supervisor' },
    { value: 'administrator', label: 'Administrador' },
    { value: 'billing', label: 'Facturación' },
    { value: 'technician', label: 'Técnico' },
] as const;

export function roleCatalogLabel(role: string): string {
    return WORKFLOW_ROLE_CATALOG.find((r) => r.value === role)?.label ?? role;
}

export const EMAIL_TOKENS = [
    { key: '{routine.id}', label: 'Identificador de rutina' },
    { key: '{routine.code}', label: 'Código de rutina' },
    { key: '{routine_type.name}', label: 'Tipo de rutina' },
    { key: '{asset.tag}', label: 'Activo (etiqueta)' },
    { key: '{asset.name}', label: 'Activo (nombre)' },
    { key: '{client.name}', label: 'Cliente' },
    { key: '{user.name}', label: 'Nombre del destinatario' },
    { key: '{routine.tasks_detail}', label: 'Detalle / tareas de la rutina' },
];

const DEFAULT_CLOSE_SUBJECT = 'Cierre de rutina {routine.id}';
const DEFAULT_CLOSE_BODY =
    '<p>Hola {user.name}, le informamos que la rutina {routine.id} del cliente {client.name} ha sido finalizada, a continuación el detalle de la rutina realizada:</p><p>{routine.tasks_detail}</p>';

const DEFAULT_ASSIGNMENT_SUBJECT = 'Nueva rutina asignada #{routine.id}';
const DEFAULT_ASSIGNMENT_BODY =
    '<p>Hola {user.name}, se te asignó la rutina {routine.id} ({routine_type.name}) en el activo {asset.tag}.</p><p>Entra a Phoenix para revisarla y ejecutarla.</p>';

export function defaultAssignmentNotify(): ActionEmailConfig {
    return {
        enabled: true,
        subject: DEFAULT_ASSIGNMENT_SUBJECT,
        body_html: DEFAULT_ASSIGNMENT_BODY,
        recipients: ['executing_technician'],
    };
}

export function defaultNotifyClose(): ActionEmailConfig {
    return {
        enabled: true,
        subject: DEFAULT_CLOSE_SUBJECT,
        body_html: DEFAULT_CLOSE_BODY,
        recipients: ['incident_creator', 'executing_technician', 'approval_supervisor'],
    };
}

export function defaultBlockGraph(): BlockGraph {
    return {
        nodes: [
            {
                id: ROUTINE_ID,
                kind: 'routine',
                label: 'Rutina',
                position: STANDARD_WORKFLOW_LAYOUT[ROUTINE_ID],
                locked: true,
                assignment_notify: defaultAssignmentNotify(),
            },
            {
                id: SUPERVISOR_STEP_ID,
                kind: 'role',
                label: 'Supervisor',
                assigned_role: 'supervisor',
                position: STANDARD_WORKFLOW_LAYOUT[SUPERVISOR_STEP_ID],
            },
            {
                id: BILLING_ID,
                kind: 'role',
                label: 'Facturación',
                assigned_role: 'billing',
                position: STANDARD_WORKFLOW_LAYOUT[BILLING_ID],
            },
            {
                id: END_ID,
                kind: 'end',
                label: 'Fin',
                position: STANDARD_WORKFLOW_LAYOUT[END_ID],
            },
        ],
        edges: [
            {
                id: 'e_revision',
                source: ROUTINE_ID,
                target: SUPERVISOR_STEP_ID,
                action: 'submit',
                label: 'Revisión',
                notify: {
                    enabled: true,
                    subject: 'Ejecuta rutina {routine.id}',
                    body_html:
                        '<p>Hola {user.name}, registramos tu ejecución de la rutina {routine.id} ({routine_type.name}) en el activo {asset.tag}.</p>',
                    recipients: ['executing_technician'],
                },
            },
            {
                id: 'e_reject',
                source: SUPERVISOR_STEP_ID,
                target: ROUTINE_ID,
                action: 'reject',
                label: 'Rechazo',
                notify: {
                    enabled: true,
                    subject: 'Rutina {routine.id} rechazada',
                    body_html:
                        '<p>Hola {user.name}, la rutina {routine.id} fue rechazada y debe volver a ejecutarse en campo.</p>',
                    recipients: ['executing_technician'],
                },
            },
            {
                id: 'e_to_billing',
                source: SUPERVISOR_STEP_ID,
                target: BILLING_ID,
                action: 'approve',
                label: 'Rutina a Facturar',
                routine_validated: true,
            },
            {
                id: 'e_invoice',
                source: BILLING_ID,
                target: END_ID,
                action: 'invoice',
                label: 'Emisión de factura',
                notify: defaultNotifyClose(),
            },
        ],
    };
}

export function graphFromDefinition(definition: WorkflowDefinition): BlockGraph {
    const version = definition.meta?.block_editor_version ?? 1;
    const stored = definition.meta?.block_graph as BlockGraph | undefined;

    if (stored?.nodes?.length && version >= 2 && !isLegacyBlockGraph(stored)) {
        return normalizeBlockGraph(cloneJson(stored), definition);
    }

    const base = defaultBlockGraph();
    hydrateEdgeMetaFromTransitions(base, definition.transitions ?? []);
    if (stored?.nodes?.length) {
        copyNodePositions(base, stored);
    } else {
        applyLayoutFromDefinition(base, definition);
    }

    return normalizeBlockGraph(base, definition);
}

export function definitionNeedsBlockGraphUpgrade(definition: WorkflowDefinition): boolean {
    const version = definition.meta?.block_editor_version ?? 1;
    const stored = definition.meta?.block_graph as BlockGraph | undefined;
    if (version < 2) {
        return true;
    }
    if (!stored?.nodes?.length) {
        return true;
    }
    return isLegacyBlockGraph(stored);
}

function isLegacyBlockGraph(graph: BlockGraph): boolean {
    const byId = Object.fromEntries(graph.nodes.map((n) => [n.id, n]));
    if (graph.nodes.some((n) => ['stage', 'email'].includes(String(n.kind)) || String(n.kind) === 'billing')) {
        return true;
    }
    return graph.edges.some(
        (e) =>
            e.action === 'handoff' ||
            e.source === STAGE_REVIEW_ID ||
            e.target === STAGE_REVIEW_ID ||
            !byId[e.source] ||
            !byId[e.target],
    );
}

function applyCanonicalLayout(graph: BlockGraph): BlockGraph {
    for (const node of graph.nodes) {
        const pos = STANDARD_WORKFLOW_LAYOUT[node.id];
        if (pos) {
            node.position = { ...pos };
        }
    }
    return graph;
}

function copyNodePositions(target: BlockGraph, source: BlockGraph): void {
    for (const node of target.nodes) {
        const from = source.nodes.find((n) => n.id === node.id);
        if (from?.position) {
            node.position = { ...from.position };
        }
    }
}

function applyLayoutFromDefinition(graph: BlockGraph, definition: WorkflowDefinition): void {
    const layout = definition.layout?.nodes ?? {};
    for (const node of graph.nodes) {
        const pos = layout[node.id];
        if (pos) {
            node.position = { ...pos };
        }
    }
    applyCanonicalLayout(graph);
}

function hydrateEdgeMetaFromTransitions(graph: BlockGraph, transitions: WorkflowDefinition['transitions']): void {
    for (const edge of graph.edges) {
        const trigger =
            edge.action === 'submit'
                ? 'execution_submitted'
                : edge.action === 'reject'
                  ? 'rejected'
                  : edge.action === 'invoice'
                    ? 'invoice_issued'
                    : 'approved';
        const match = transitions.find((t) => t.from === edge.source && t.trigger === trigger);
        if (!match) {
            continue;
        }
        if (typeof match.label === 'string' && match.label.trim()) {
            edge.label = match.label;
        }
        if (match.actions?.includes('routine_validated')) {
            edge.routine_validated = true;
        }
        if (match.notify && typeof match.notify === 'object') {
            edge.notify = match.notify as ActionEmailConfig;
        }
    }
}

export function normalizeBlockGraph(graph: BlockGraph, definition?: WorkflowDefinition): BlockGraph {
    const layout = definition?.layout?.nodes ?? {};
    let routine = graph.nodes.find((n) => n.id === ROUTINE_ID);

    if (!routine) {
        const step = definition?.steps?.[ROUTINE_ID];
        routine = {
            id: ROUTINE_ID,
            kind: 'routine',
            label: typeof step?.label === 'string' ? step.label : 'Rutina',
            position: layout[ROUTINE_ID] ?? { x: 48, y: 160 },
            locked: true,
        };
        graph.nodes = [routine, ...graph.nodes];
    } else {
        routine.kind = 'routine';
        routine.locked = true;
        routine.label = routine.label || 'Rutina';
        if (!routine.position || (routine.position.x === 0 && routine.position.y === 0)) {
            routine.position = layout[ROUTINE_ID] ?? { x: 48, y: 160 };
        }
        const stepNotify = definition?.steps?.[ROUTINE_ID]?.assignment_notify;
        if (!routine.assignment_notify && stepNotify && typeof stepNotify === 'object') {
            routine.assignment_notify = stepNotify as ActionEmailConfig;
        }
    }

    if (!graph.nodes.some((n) => n.id === END_ID)) {
        graph.nodes.push({
            id: END_ID,
            kind: 'end',
            label: 'Fin',
            position: layout[END_ID] ?? { x: 800, y: 160 },
        });
    }

    for (const edge of graph.edges) {
        if (!edge.label) {
            edge.label = defaultLabelForAction(edge.action);
        }
    }

    graph.nodes = graph.nodes
        .filter((n) => !['stage', 'email'].includes(String(n.kind)))
        .map((n) => {
            if (String(n.kind) === 'billing' || (n.id === BILLING_ID && n.kind !== 'end')) {
                return {
                    ...n,
                    kind: 'role' as const,
                    assigned_role: n.assigned_role ?? 'billing',
                };
            }
            return n;
        });

    graph.edges = graph.edges.filter((e) => {
        const byId = Object.fromEntries(graph.nodes.map((n) => [n.id, n]));
        return Boolean(byId[e.source] && byId[e.target]);
    });

    const submits = graph.edges.filter((e) => e.source === ROUTINE_ID && e.action === 'submit');
    if (submits.length > 1) {
        const keep = submits[0];
        graph.edges = graph.edges.filter(
            (e) => !(e.source === ROUTINE_ID && e.action === 'submit' && e.id !== keep.id),
        );
    }

    return graph;
}

function defaultLabelForAction(action: BlockAction): string {
    const map: Record<BlockAction, string> = {
        submit: 'Revisión',
        approve: 'Aprobar',
        reject: 'Rechazo',
        invoice: 'Emisión de factura',
    };
    return map[action];
}

function inferGraphFromDefinition(definition: WorkflowDefinition): BlockGraph {
    const nodes: BlockNode[] = Object.entries(definition.steps).map(([id, meta]) => {
        const kind = inferKind(id, meta);
        const node: BlockNode = {
            id,
            kind,
            label: id === ROUTINE_ID ? 'Rutina' : meta.label,
            position: definition.layout?.nodes?.[id] ?? { x: 280, y: 160 },
            locked: id === ROUTINE_ID,
        };
        if (kind === 'role') {
            node.assigned_role = meta.assigned_role ?? 'supervisor';
        }
        return node;
    });

    const edges: BlockEdge[] = definition.transitions.map((t, index) => {
        const action = triggerToAction(t.trigger);
        const edge: BlockEdge = {
            id: `e-${index}`,
            source: t.from,
            target: t.to,
            action,
            label: typeof t.label === 'string' ? t.label : defaultLabelForAction(action),
            routine_validated: t.actions?.includes('routine_validated'),
        };
        if (t.notify && typeof t.notify === 'object') {
            edge.notify = t.notify as ActionEmailConfig;
        }
        return edge;
    });

    return { nodes, edges };
}

function triggerToAction(trigger: string): BlockAction {
    if (trigger === 'execution_submitted') {
        return 'submit';
    }
    if (trigger === 'rejected') {
        return 'reject';
    }
    if (trigger === 'invoice_issued') {
        return 'invoice';
    }
    return 'approve';
}

function inferKind(id: string, meta: { type: string; assigned_role?: string }): BlockKind {
    if (id === ROUTINE_ID) {
        return 'routine';
    }
    if (id === END_ID || meta.type === 'end') {
        return 'end';
    }
    return 'role';
}

export function compileBlockGraph(graph: BlockGraph, base?: WorkflowDefinition): WorkflowDefinition {
    const normalized = normalizeBlockGraph(cloneJson(graph), base);
    validateGraph(normalized);

    const steps: WorkflowDefinition['steps'] = {};
    const layoutNodes: Record<string, { x: number; y: number }> = {};

    for (const node of normalized.nodes) {
        layoutNodes[node.id] = { ...node.position };
        steps[node.id] = stepFromNode(node);
    }

    const transitions = normalized.edges.map((edge) => {
        const trigger =
            edge.action === 'submit'
                ? 'execution_submitted'
                : edge.action === 'reject'
                  ? 'rejected'
                  : edge.action === 'invoice'
                    ? 'invoice_issued'
                    : 'approved';

        const t: WorkflowDefinition['transitions'][0] = {
            from: edge.source,
            to: edge.target,
            trigger,
            label: edge.label,
        };
        if (edge.action === 'approve' && edge.routine_validated) {
            t.actions = ['routine_validated'];
        }
        if (edge.notify?.enabled) {
            t.notify = edge.notify;
        }
        return t;
    });

    const meta = { ...(base?.meta ?? {}), block_editor_version: 2, block_graph: normalized };

    return {
        initial_step: ROUTINE_ID,
        steps,
        transitions,
        layout: { nodes: layoutNodes },
        meta,
    };
}

function stepFromNode(node: BlockNode): WorkflowDefinition['steps'][string] {
    switch (node.kind) {
        case 'routine': {
            const step: WorkflowDefinition['steps'][string] = {
                type: 'human_task',
                label: node.label,
                assigned_role: 'technician',
            };
            if (node.assignment_notify?.enabled) {
                step.assignment_notify = node.assignment_notify;
            }
            return step;
        }
        case 'role':
            return {
                type: 'human_task',
                label: node.label,
                assigned_role: node.assigned_role ?? 'supervisor',
            };
        case 'end':
            return { type: 'end', label: node.label };
        default:
            return { type: 'human_task', label: node.label };
    }
}

export function validateGraph(graph: BlockGraph): void {
    const byId = Object.fromEntries(graph.nodes.map((n) => [n.id, n]));
    if (!byId[ROUTINE_ID]) {
        throw new Error('Falta el bloque Rutina.');
    }

    for (const edge of graph.edges) {
        if (!byId[edge.source] || !byId[edge.target]) {
            throw new Error('Arista con nodo inexistente.');
        }
        const sk = byId[edge.source].kind;
        if (edge.action === 'reject' && (sk !== 'role' || edge.target !== ROUTINE_ID)) {
            throw new Error('Rechazo solo desde Rol hacia Rutina.');
        }
        if (edge.action === 'approve') {
            if (sk !== 'role') {
                throw new Error('Aprobar solo desde Rol.');
            }
            if (edge.target === ROUTINE_ID) {
                throw new Error('Aprobar no puede apuntar a Rutina.');
            }
        }
        if (edge.action === 'submit' && edge.source !== ROUTINE_ID) {
            throw new Error('Enviar solo desde Rutina.');
        }
        if (edge.action === 'invoice') {
            if (sk !== 'role' || byId[edge.target].kind !== 'end') {
                throw new Error('Emisión de factura solo desde Rol de facturación hacia Fin.');
            }
        }
    }

    const submits = graph.edges.filter((e) => e.source === ROUTINE_ID && e.action === 'submit');
    if (submits.length > 1) {
        throw new Error('Rutina solo puede tener una salida de envío.');
    }
}

export function newRoleNode(assignedRole = 'supervisor'): BlockNode {
    const id = `role_${assignedRole}_${Math.random().toString(36).slice(2, 6)}`;
    return {
        id,
        kind: 'role',
        label: roleCatalogLabel(assignedRole),
        assigned_role: assignedRole,
        position: { x: 400, y: 160 },
    };
}

export function edgeDisplayLabel(edge: BlockEdge): string {
    return edge.label || defaultLabelForAction(edge.action);
}

export function defaultEdgeForAction(action: BlockAction): Pick<BlockEdge, 'action' | 'label' | 'routine_validated' | 'notify'> {
    if (action === 'submit') {
        return {
            action,
            label: 'Revisión',
            notify: {
                enabled: true,
                subject: 'Ejecución de rutina {routine.id}',
                body_html: '<p>Hola {user.name}, registramos la ejecución de la rutina {routine.id}.</p>',
                recipients: ['executing_technician'],
            },
        };
    }
    if (action === 'reject') {
        return {
            action,
            label: 'Rechazo',
            notify: {
                enabled: true,
                subject: 'Rutina {routine.id} rechazada',
                body_html: '<p>Hola {user.name}, la rutina debe volver a ejecutarse en campo.</p>',
                recipients: ['executing_technician'],
            },
        };
    }
    if (action === 'invoice') {
        return {
            action,
            label: 'Emisión de factura',
            notify: defaultNotifyClose(),
        };
    }
    return { action, label: 'Aprobar', routine_validated: false };
}
