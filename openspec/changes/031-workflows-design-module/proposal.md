# 031 — Módulo de diseño de workflows (salir de solo lectura)

## Problema

El **motor de workflow** ya opera en producción (instancia por rutina, transiciones al enviar/validar/rechazar, acción `routine_validated` → PDF y borrador de factura). El **diseñador visual** (010) permite guardar layout y etiquetas de pasos.

Sin embargo, el módulo **se percibe como solo lectura** frente a Formularios y Reportes:

| Área | Hoy |
|------|-----|
| Listado (`WorkflowsListPage`) | Solo GET; sin crear, sin aviso de permisos, UI mínima |
| API | `GET` + `PUT …/definition`; no `POST` ni metadatos |
| Catálogo | Un flujo sembrado por empresa (`routine-validation-v1`); no duplicar ni nombrar variantes |
| Grafo | Transiciones y acciones fijas en JSON; documentadas como “solo lectura” en el canvas |
| Enlace operativo | Tipos de rutina ya asignan `workflow_definition_id`; falta visibilidad de **uso** en listado |

Referencias: [domain/workflows.md](../../domain/workflows.md), [workflow-engine.md](../../architecture/workflow-engine.md), archivo `005-workflow-runtime-v1`, `010-workflow-designer-visual`.

## Objetivo

Cerrar el **módulo de diseño** `design_workflows` con paridad MVP respecto a otros diseñadores: listar, **crear** flujos a partir de la plantilla estándar, **duplicar** uno existente, editar metadatos y diseño (layout + labels), con validación server-side alineada con `WorkflowRuntime`, tests y UX portal (permisos, toasts, componentes compartidos).

El **runtime lineal v1** no se reescribe: siguen los tres disparadores (`execution_submitted`, `approved`, `rejected`) y la topología canónica campo → supervisor → cierre (con bucle de rechazo).

## Alcance v1

### Backend

- `POST /design/workflows` — crear definición (`name`, `slug` opcional) con `WorkflowRuntime::defaultDefinition()`, `status: published`, `version: 1`.
- `POST /design/workflows/{id}/duplicate` — copia nombre “(copia)”, nuevo `slug` único, misma `definition` (layout incluido).
- `PATCH /design/workflows/{id}` — actualizar `name` (y `slug` si se expone en UI, con unicidad por empresa).
- Servicio `WorkflowDefinitionValidator` (o equivalente): al `PUT …/definition` y al crear/duplicar, comprobar:
  - `initial_step` existe en `steps`;
  - cada transición referencia pasos existentes;
  - disparadores permitidos en v1;
  - desde cada paso humano salen transiciones coherentes con el runtime (mínimo: grafo por defecto o isomorfo funcional);
  - acciones en `approved` ⊆ `{ routine_validated }` (lista cerrada v1).
- `GET /design/workflows` enriquecido: `routine_types_count` (o lista corta de tipos que lo usan) para evitar borrar/duplicar a ciegas.
- Auditoría en create/duplicate/update metadata (mismo patrón que formularios).
- Mutaciones protegidas por `company.module:design_workflows,write` (mantener `authorizeDesigner` = Administrador como en formularios/reportes hasta un cambio transversal de RBAC).

### Frontend

- `WorkflowsListPage`: `PageHeader`, `AppButton`, `ReadOnlyNotice`, `useModuleAccess` (`canWriteModule('design_workflows')`).
- Acciones: **Nuevo workflow** (modal o panel inline al estilo `ClientsPage` / `FormsListPage`), **Duplicar** por fila.
- `WorkflowDesignerPage`: cabecera portal (`PageHeader`), `AppButton` guardar, estilos `--portal-*`; deshabilitar guardar si solo lectura.
- Sección “Reglas del motor”: mantener solo lectura de **transiciones** en v1; texto de ayuda que indique que crear otro workflow o duplicar es el camino para variantes futuras (versionado completo queda para después).
- Opcional acotado en diseñador: checkbox “Al validar: generar PDF y borrador de factura” que solo togglea `actions: ['routine_validated']` en la transición `approved` → `complete` (sin editor de grafo).

### Documentación y demo

- Actualizar `docs/IMPLEMENTATION.md` (o sección en `openspec/domain/workflows.md`) con contrato API y reglas del validador.
- Demo: `NoahDemoSeeder` / `noah:refresh-demo` sigue ofreciendo al menos un workflow publicado; opcional segundo workflow de ejemplo duplicado.

### Tests

- Feature: crear, duplicar, validación rechaza definición rota, usuario sin módulo write → 403, layout update existente (extender `WorkflowDesignerApiTest`).

## Fuera de alcance (v1)

- Editor de grafo (añadir/quitar nodos, aristas arbitrarias, gateways, condiciones).
- Versionado publicado/borrador al estilo `FormVersion` (múltiples `version` por `slug` con migración de instancias en curso).
- Nodos `service_task` / `ai_task` configurables en UI.
- Sustituir `authorizeDesigner` (Administrador) por permisos granulares en todos los diseñadores (cambio transversal 032+).

## Criterios de aceptación

1. Usuario con permiso de escritura en `design_workflows` y rol Administrador puede crear y duplicar workflows desde la UI; el listado muestra nombre, slug, versión y cuántos tipos de rutina lo referencian.
2. Guardar diseño con layout/labels inválidos o grafo incompatible con v1 devuelve 422 con mensaje claro.
3. Rutinas existentes siguen transicionando igual con el workflow por defecto; desactivar `routine_validated` en un workflow solo afecta rutinas que usen esa definición tras validar.
4. Usuario solo lectura ve listado y diseñador sin botones de guardar/crear y con `ReadOnlyNotice`.
5. Tests Feature cubren create, duplicate y validación; CI verde.

## Riesgos y mitigación

- **Editar grafo sin validar** rompe ejecuciones → validador estricto v1; transiciones no editables salvo toggle de acción en `approved`.
- **Borrar workflow en uso** → no implementar delete en v1; solo create/duplicate/metadata.
- **Instancias atadas a `workflow_definition_id`** → duplicar crea nueva fila; tipos de rutina deben reasignarse manualmente (documentado).
