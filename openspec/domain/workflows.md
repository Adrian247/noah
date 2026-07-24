# Dominio — Workflows (Noah)

Motor: [workflow-engine.md](../architecture/workflow-engine.md).

## Agregados

### WorkflowDefinition

- Versión publicada de un flujo asociado a RoutineType o global por empresa.
- Grafo: nodos (estado, tarea, gateway) y aristas (condiciones).

### WorkflowInstance

- Ligada a Routine o Execution.
- Estado actual, historial de transiciones, asignaciones pendientes.

## Nodos típicos (v1)

| Tipo | Acción |
|------|--------|
| `start` | Inicio |
| `human_task` | Validación supervisor |
| `service_task` | Generar PDF, crear borrador factura |
| `ai_task` | Corrección gramatical |
| `end` | Cierre |

## Condiciones

Expresiones sobre datos de ejecución (ej. `costo_total > 50000` → aprobación adicional). Motor de reglas avanzado: [rules-engine.md](../architecture/rules-engine.md).

## Invariantes

- Transiciones registradas en auditoría.
- No saltar validación humana si el flujo la define.

## Eventos

- `WorkflowStarted`, `WorkflowTransitioned`, `WorkflowCompleted`, `WorkflowFailed`
