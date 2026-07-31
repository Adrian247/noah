# 033 — Plantillas y workflows personalizables (Fases 1–4)

## Problema

Tras 031/032, crear un workflow solo clona `defaultDefinition()` (campo → supervisor → facturación → cierre). No hay elección de plantilla ni opciones de proceso; el diseñador no puede adaptar topología sin duplicar el mismo grafo.

## Objetivo

Catálogo de plantillas al crear, panel de configuración (facturación, PDF/borrador, doble revisión), validación genérica del grafo, runtime acoplado a la definición, y ciclo borrador/publicado sin romper instancias en curso.

## Fases

### Fase 1 — Plantillas al crear

- `GET /design/workflows/templates`
- `POST /design/workflows` acepta `template` + `options`
- Plantillas: `standard_billing`, `classic_no_billing`, `validation_only`, `dual_review`

### Fase 2 — Configuración en diseñador

- `PUT /design/workflows/{id}/configure` — reconstruye grafo desde `meta.options`
- Opciones: `include_billing`, `routine_validated_on_approve`, `dual_review`
- Pasos con `assigned_role` (technician, supervisor, billing)

### Fase 3 — Personalización del grafo (MVP)

- Editor de transiciones en UI (tabla from/to/trigger/actions)
- `PUT …/definition` acepta grafo válido validado por reglas genéricas

### Fase 4 — Borrador / publicado

- Alta por defecto en `draft`
- `POST …/publish` — valida y publica
- Edición de definición solo en `draft`; tipos de rutina solo workflows `published`

## Fuera de alcance

- Vue Flow libre, gateways, condiciones por campo de formulario
- Borrado de definiciones en uso

**Siguiente:** diseñador por bloques (etapas / roles / acciones) — ver [036-workflow-block-designer](../changes/036-workflow-block-designer/proposal.md).

## Criterios de aceptación

1. Admin crea workflow «sin facturación» y rutina validada cierra sin paso `billing_review`.
2. Emisión de factura exige paso facturación solo si el grafo lo define.
3. Borrador no asignable a tipo de rutina hasta publicar.
4. Tests Feature plantillas + publish + flujo sin billing.
