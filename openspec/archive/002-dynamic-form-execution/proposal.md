# Propuesta 002 — Captura dinámica de formulario y consumos (Fase 1)

## Objetivo

Completar el flujo de **ejecución de rutina** en web: renderizar el esquema del `FormVersion` ligado al tipo de rutina y registrar **consumos de insumos** en la API.

## Alcance

- API: `POST /routines/{id}/executions` acepta `consumptions[]`; `GET /routines/{id}` incluye `routineType.formVersion`.
- Componente `DynamicFormRenderer` (campos `number`, `textarea`, `text`).
- Pantalla detalle de rutina: formulario + selección de insumos + duración/comentarios.

## Referencias

- [forms-engine.md](../../architecture/forms-engine.md), [domain/forms.md](../../domain/forms.md), [domain/inventory.md](../../domain/inventory.md)
