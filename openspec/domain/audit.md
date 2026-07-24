# Dominio — Audit (Noah)

## Responsabilidad

Registro append-only de acciones relevantes para cumplimiento y soporte.

## Agregado

### AuditEntry

- `company_id`, `actor_user_id`, `action`, `subject_type`, `subject_id`, `metadata` (JSON), `ip`, `occurred_at`.

## Qué auditar (mínimo)

- Login/logout, cambios de permisos.
- Publicación de formularios, plantillas, workflows.
- Validación/rechazo de rutinas.
- Emisión/cancelación de facturas.
- Invocaciones IA (resumen; detalle en AIInvocation).
- Sync batches significativos (resumen).

## Qué no duplicar

- Logs técnicos de aplicación → observability.
- Historial de workflow → WorkflowInstance.

## Retención

Configurable por empresa; por defecto ≥ 1 año en producción (NFR).

## Invariantes

- Sin update/delete en AuditEntry (solo inserción).
