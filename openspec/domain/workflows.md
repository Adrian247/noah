# Dominio — Workflows (Noah)

Motor: [workflow-engine.md](../architecture/workflow-engine.md).

## Agregados

### WorkflowDefinition

- Versión publicada de un flujo asociado a RoutineType o global por empresa.
- Grafo: nodos (estado, tarea, gateway) y aristas (condiciones).
- `definition.meta`: `template` + `options` (facturación, doble revisión, PDF/borrador).
- Ciclo de vida: `draft` → `published` (solo publicados asignables a tipos de rutina).

### WorkflowInstance

- Ligada a Routine o Execution.
- Estado actual, historial de transiciones, asignaciones pendientes.

## Plantillas (033)

| Clave | Uso |
|-------|-----|
| `standard_billing` | Campo → supervisor → facturación → cierre |
| `classic_no_billing` | Campo → supervisor → cierre (PDF/borrador al aprobar) |
| `validation_only` | Campo → supervisor → cierre sin acciones automáticas |
| `dual_review` | Campo → supervisor → jefe → facturación (opcional) → cierre |

## Nodos típicos (v1)

| Tipo | Acción |
|------|--------|
| `human_task` | Tarea humana (`assigned_role`: technician, supervisor, billing) |
| `end` | Cierre |
| `service_task` | Reservado (futuro) |

## API diseño

- `GET /design/workflows/templates` — catálogo de plantillas.
- `GET/POST /api/v1/design/workflows` — listado y alta (`template`, `options`); alta en `draft`.
- `PUT …/configure` — reconstruir grafo desde `meta.options` (solo borrador).
- `POST …/publish` — publicar tras validación.
- `POST /design/workflows/{id}/duplicate` — copia en borrador.
- `PATCH /design/workflows/{id}` — metadatos (`name`, `slug` opcional).
- `PUT …/definition` — layout, labels, transiciones (solo borrador).

## Invariantes

- Transiciones registradas en auditoría.
- Validador: grafo alcanzable, pasos humanos con `approved`/`rejected` (excepto facturación).
- Emisión de factura exige paso `billing_review` solo si el grafo lo define.

## Eventos

- `WorkflowStarted`, `WorkflowTransitioned`, `WorkflowCompleted`, `WorkflowFailed`
