# Tareas 031 — Workflows diseño (salir de solo lectura)

## API y dominio

- [x] `WorkflowDefinitionValidator` con reglas v1 (pasos, transiciones, triggers, acciones).
- [x] Integrar validador en `updateDefinition` y en payloads de create/duplicate.
- [x] `POST /design/workflows` (create desde plantilla `defaultDefinition`).
- [x] `POST /design/workflows/{workflowDefinition}/duplicate`.
- [x] `PATCH /design/workflows/{workflowDefinition}` (nombre; slug opcional).
- [x] Enriquecer `index` con `routine_types_count` (eager `withCount('routineTypes')`).
- [x] Auditoría: `workflow.created`, `workflow.duplicated`, `workflow.updated`.
- [x] Rutas en `routes/api.php` con middleware `design_workflows` read/write.

## UI

- [x] `WorkflowsListPage`: permisos, `ReadOnlyNotice`, crear (modal/inline), duplicar, badges de uso.
- [x] `WorkflowDesignerPage`: `PageHeader`, tokens portal, `AppButton`, guardar condicionado a `canWrite`.
- [x] Toggle UI “PDF + prefactura al validar” → `actions` en transición `approved` (si encaja en validador).
- [x] Mensajes vía `useToast` en todas las mutaciones.

## Datos y docs

- [x] Revisar seed/demo: workflow por defecto + documentar en `docs/IMPLEMENTATION.md` o `domain/workflows.md`.
- [x] Sin `DELETE` en v1 (comentario en proposal cumplido).

## Tests

- [x] `WorkflowDesignerApiTest`: create, duplicate, 422 definición inválida.
- [x] Permiso: write sin admin sigue 403 en mutación (comportamiento actual documentado) o skip si se alinea RBAC.
- [x] Regresión: test runtime existente (`WorkflowRuntimeTest`) sin cambios de comportamiento por defecto.

## Cierre

- [x] Manual rápido en `docs/PRUEBAS_MANUALES.md` (opcional): crear workflow, asignar en tipo de rutina, ejecutar rutina.
- [ ] Archivar a `openspec/archive/031-workflows-design-module/` al mergear.
