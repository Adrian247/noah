const ACTION_LABELS: Record<string, string> = {
    'auth.login': 'Inicio de sesión',
    'auth.logout': 'Cierre de sesión',
    'routine.created': 'Servicio creado',
    'routine.deleted': 'Servicio eliminado',
    'routine.execution_submitted': 'Ejecución enviada',
    'routine.validated': 'Servicio validado',
    'routine.rejected': 'Servicio rechazado',
    'workflow.transitioned': 'Transición de workflow',
    'workflow.created': 'Workflow creado',
    'workflow.updated': 'Workflow actualizado',
    'workflow.published': 'Workflow publicado',
    'workflow.configured': 'Workflow configurado',
    'workflow.duplicated': 'Workflow duplicado',
    'workflow.deleted': 'Workflow eliminado',
    'workflow.definition_updated': 'Definición de workflow actualizada',
    'invoice.issued': 'Factura emitida',
    'invoice.delivered_to_client': 'Documentación entregada al cliente',
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
    'platform.role_permissions_updated': 'Permisos de roles de plataforma actualizados',
    'integrations.mcp_token_created': 'Token MCP creado',
    'integrations.mcp_token_revoked': 'Token MCP revocado',
    'predictive.algorithm_trained': 'Algoritmo predictivo entrenado',
    'predictive.algorithm_published': 'Algoritmo predictivo publicado',
    'predictive.algorithm_archived': 'Algoritmo predictivo archivado',
    'predictive.algorithm_notes_updated': 'Notas de algoritmo predictivo actualizadas',
    'predictive.training_document_uploaded': 'Documento de entrenamiento subido',
    'predictive.training_document_deleted': 'Documento de entrenamiento eliminado',
    'predictive.company_settings_updated': 'Ajustes predictivos de empresa actualizados',
    'membership.granted': 'Membresía otorgada',
    'membership.updated': 'Membresía actualizada',
    'membership.permissions_updated': 'Permisos de membresía actualizados',
    'user.password_reset': 'Contraseña restablecida',
    'user.avatar_updated': 'Avatar actualizado',
    'client.created': 'Cliente creado',
    'client.updated': 'Cliente actualizado',
    'client.deleted': 'Cliente eliminado',
    'portal.updated': 'Portal actualizado',
    'portal.invoice_downloaded': 'Factura descargada desde portal',
    'portal.report_downloaded': 'Reporte descargado desde portal',
    'asset.client_linked': 'Artículo vinculado a cliente',
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
