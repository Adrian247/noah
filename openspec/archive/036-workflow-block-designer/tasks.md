# 036 — Tareas

## Fase 1 — Compilador y contrato

- [x] Tipos TS `BlockGraph` + `workflowBlockModel.ts`
- [x] `WorkflowBlockCompiler` (PHP) + tests unitarios
- [x] Matriz Aprobar/Rechazar en compilador

## Fase 2 — UI canvas

- [x] `WorkflowBlockCanvas` (Vue Flow, Rutina fija, paleta)
- [x] Quitar configure + transiciones en `WorkflowDesignerPage`

## Fase 3 — Aprobar / Rechazar

- [x] Aristas tipadas + `routine_validated` en inspector de arista

## Fase 4 — Email

- [x] Panel TipTap + tokens en modelo
- [x] `WorkflowEmailBodyRenderer` en runtime

## Fase 5 — API

- [x] Compilación en `PUT …/definition` si hay `block_graph`

## Fase 6 — Cliente prefactura

- [x] `RoutineInvoiceClientResolver` + draft con `client_id`
- [x] Test unitario resolver

## Pendiente / mejoras

- [x] Migración automática al abrir diseñador (`GET /design/workflows/{id}` + `ensureEditorDefinition`)
- [x] Vista HTML en email (blade) para cuerpo enriquecido (`mail.workflow-step-html`)
- [x] Tests Feature diseño workflow por bloques (`WorkflowBlockDesignerApiTest`)
