export type StepMeta = {
    type: string;
    label: string;
    assigned_role?: string;
    task?: string;
    email?: {
        roles?: string[];
        template?: string;
        subject?: string;
        message?: string;
    };
};

export type Transition = {
    from: string;
    to: string;
    trigger: string;
    label?: string;
    actions?: string[];
    notify?: Record<string, unknown>;
};

export type WorkflowDefinition = {
    initial_step: string;
    steps: Record<string, StepMeta>;
    transitions: Transition[];
    layout: { nodes: Record<string, { x: number; y: number }> };
    meta?: {
        template?: string;
        options?: Record<string, boolean>;
        block_graph?: unknown;
        block_editor_version?: number;
    };
};

export type FlowNode = {
    id: string;
    type: string;
    position: { x: number; y: number };
    data: StepMeta & { stepId: string };
};

export type FlowEdge = {
    id: string;
    source: string;
    target: string;
    label?: string;
    data?: { trigger: string; actions?: string[] };
};

export function definitionToFlow(definition: WorkflowDefinition): { nodes: FlowNode[]; edges: FlowEdge[] } {
    const nodes: FlowNode[] = Object.entries(definition.steps).map(([id, meta], index) => ({
        id,
        type: nodeTypeForStep(meta),
        position: definition.layout?.nodes?.[id] ?? { x: 80 + index * 200, y: 120 },
        data: { ...meta, stepId: id },
    }));

    const edges: FlowEdge[] = definition.transitions.map((t, index) => ({
        id: `e-${t.from}-${t.trigger}-${index}`,
        source: t.from,
        target: t.to,
        label: triggerLabel(t.trigger),
        data: { trigger: t.trigger, actions: t.actions },
    }));

    return { nodes, edges };
}

function nodeTypeForStep(meta: StepMeta): string {
    if (meta.type === 'end') {
        return 'end';
    }
    if (meta.type === 'service_task' && meta.task === 'send_email') {
        return 'email';
    }

    return 'human';
}

export function flowToDefinition(
    nodes: FlowNode[],
    edges: FlowEdge[],
    base: WorkflowDefinition,
): WorkflowDefinition {
    const steps: Record<string, StepMeta> = {};
    const layoutNodes: Record<string, { x: number; y: number }> = {};

    for (const node of nodes) {
        const { stepId, ...meta } = node.data;
        steps[node.id] = meta;
        layoutNodes[node.id] = { ...node.position };
    }

    const transitions: Transition[] = edges.map((edge) => ({
        from: edge.source,
        to: edge.target,
        trigger: edge.data?.trigger ?? 'approved',
        actions: edge.data?.actions,
    }));

    return {
        ...base,
        initial_step: base.initial_step && steps[base.initial_step] ? base.initial_step : nodes[0]?.id ?? 'field_execution',
        steps,
        transitions,
        layout: { nodes: layoutNodes },
    };
}

export function triggerLabel(trigger: string): string {
    const map: Record<string, string> = {
        execution_submitted: 'Técnico envía',
        approved: 'Aprueba',
        rejected: 'Rechaza',
        invoice_issued: 'Factura emitida',
        service_complete: 'Email enviado',
    };

    return map[trigger] ?? trigger;
}

export const TRIGGER_OPTIONS = [
    { value: 'execution_submitted', label: 'Técnico envía' },
    { value: 'approved', label: 'Aprueba' },
    { value: 'rejected', label: 'Rechaza' },
    { value: 'invoice_issued', label: 'Factura emitida' },
    { value: 'service_complete', label: 'Tras email (auto)' },
];

export function newEmailStepId(): string {
    return `email_${Math.random().toString(36).slice(2, 8)}`;
}

export function createEmailStep(): { id: string; meta: StepMeta } {
    const id = newEmailStepId();

    return {
        id,
        meta: {
            type: 'service_task',
            label: 'Enviar email',
            task: 'send_email',
            email: {
                roles: ['supervisor', 'administrator'],
                template: 'routine_pending_validation',
                subject: 'Noah — Notificación de rutina',
                message: 'Hay una rutina que requiere tu atención.',
            },
        },
    };
}
