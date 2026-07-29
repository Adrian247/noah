const ACTION_LABELS: Record<string, string> = {
    'auth.login': 'Inicio de sesión',
    'auth.logout': 'Cierre de sesión',
    'routine.created': 'Rutina creada',
    'routine.deleted': 'Rutina eliminada',
    'routine.execution_submitted': 'Ejecución enviada',
    'routine.validated': 'Rutina validada',
    'routine.rejected': 'Rutina rechazada',
    'workflow.transitioned': 'Transición de workflow',
    'workflow.created': 'Workflow creado',
    'workflow.updated': 'Workflow actualizado',
    'workflow.published': 'Workflow publicado',
    'workflow.configured': 'Workflow configurado',
    'workflow.duplicated': 'Workflow duplicado',
    'workflow.deleted': 'Workflow eliminado',
    'workflow.definition_updated': 'Definición de workflow actualizada',
    'invoice.issued': 'Factura emitida',
    'invoice.draft_updated': 'Borrador de factura actualizado',
    'report.template_renamed': 'Plantilla de reporte renombrada',
    'report.components_updated': 'Componentes de reporte actualizados',
    'report.cover_image_updated': 'Portada de reporte actualizada',
    'report.cover_image_removed': 'Portada de reporte eliminada',
    'report.version_published': 'Versión de reporte publicada',
    'report.template_deleted': 'Plantilla de reporte eliminada',
    'report.preset_applied': 'Plantilla de diseño aplicada al reporte',
    'form.option_catalog_created': 'Catálogo de opciones creado',
    'form.option_catalog_updated': 'Catálogo de opciones actualizado',
    'form.option_catalog_deleted': 'Catálogo de opciones eliminado',
    'form.settings_updated': 'Ajustes de formularios actualizados',
    'platform.tenant_assumed': 'Acceso a tenant como plataforma',
    'platform.tenant_updated': 'Cliente de plataforma actualizado',
    'platform.tenant_logo_updated': 'Logo de cliente de plataforma actualizado',
};

export function auditActionLabel(action: string): string {
    if (ACTION_LABELS[action]) {
        return ACTION_LABELS[action];
    }

    const humanized = action
        .replaceAll('.', ' · ')
        .replaceAll('_', ' ');

    return humanized.charAt(0).toUpperCase() + humanized.slice(1);
}
