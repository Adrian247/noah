const STEP_LABELS: Record<string, string> = {
    start: 'Inicio',
    field_work: 'Trabajo en campo',
    field_execution: 'Ejecución en campo',
    supervisor_review: 'Revisión de supervisor',
    validation: 'Validación',
    billing_review: 'Revisión de facturación',
    billing: 'Facturación',
    invoice: 'Factura emitida',
    completed: 'Completado',
    done: 'Finalizado',
};

const TRIGGER_LABELS: Record<string, string> = {
    routine_assigned: 'Servicio asignado',
    execution_submitted: 'Ejecución enviada',
    routine_validated: 'Servicio validado',
    routine_rejected: 'Servicio rechazado',
    invoice_issued: 'Factura emitida',
    workflow_started: 'Workflow iniciado',
};

export function clientPortalStepLabel(key: string | null | undefined): string {
    if (!key) {
        return '—';
    }
    if (STEP_LABELS[key]) {
        return STEP_LABELS[key];
    }
    return key.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function clientPortalTriggerLabel(trigger: string): string {
    if (TRIGGER_LABELS[trigger]) {
        return TRIGGER_LABELS[trigger];
    }
    return trigger.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function formatPortalDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat('es-MX', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

export function formatPortalMoney(amount: string | number, currency: string): string {
    const value = typeof amount === 'string' ? Number(amount) : amount;
    if (Number.isNaN(value)) {
        return `${amount} ${currency}`;
    }
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: currency || 'MXN',
        minimumFractionDigits: 2,
    }).format(value);
}

export function reportStatusLabel(status: string): string {
    const map: Record<string, string> = {
        ready: 'Listo',
        queued: 'En cola',
        processing: 'Generando',
        failed: 'Error',
    };
    return map[status] ?? status;
}
