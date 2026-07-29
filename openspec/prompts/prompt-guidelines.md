# Guía de prompts — Phoenix

## Principios

1. Prompts viven en **Prompt Registry** (BD), no hardcodeados en código de dominio.
2. Versionar cada cambio; activar una versión por empresa o global.
3. IA **asiste**; reglas críticas en workflow y rules engine.
4. Auditar invocaciones (AIInvocation).

## Estructura de un prompt registrado

| Campo | Descripción |
|-------|-------------|
| `slug` | Identificador estable |
| `version` | Entero incremental |
| `system` | Instrucciones fijas |
| `user_template` | Con placeholders `{{var}}` |
| `model` | Sugerencia; Gateway puede override |
| `temperature` | Baja para tareas deterministas |

## Variables

- Documentar cada variable en el template.
- Sanitizar entrada (longitud máxima, strip HTML).

## Casos documentados

| Slug | Archivo |
|------|---------|
| grammar_correction_v1 | [grammar-correction.md](grammar-correction.md) |

## Futuros (no implementar en v1)

- `report_generation_v1` — solo si se define extracción estricta desde campos.
- `image_analysis_v1` — fase 4.

## Proveedores

Solo vía [AI Gateway](../architecture/ai-gateway.md).
