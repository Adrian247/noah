# Dominio — AI (Phoenix)

Capa de dominio delimitada para casos de uso IA. Implementación: [ai-gateway.md](../architecture/ai-gateway.md).

## Agregados (Gateway)

### PromptTemplate

- Nombre, versión, system prompt, user template, variables, modelo sugerido, temperatura.

### AIInvocation

- `company_id`, tipo de caso (`grammar_correction`, …), entrada hash, salida, proveedor, tokens, costo estimado, `invoked_by` (usuario o workflow).

## Casos de uso v1

| Caso | Entrada | Salida |
|------|---------|--------|
| Grammar correction | Texto técnico crudo | Texto corregido sin hechos nuevos |

## Políticas

- Validador post-respuesta: longitud, prohibición de frases que impliquen datos no presentes (heurística + revisión humana en validación).
- Retención de prompts y logs según política de empresa.

## Eventos

- `GrammarCorrectionRequested`, `GrammarCorrectionCompleted`, `AIInvocationFailed`

## Fuera de alcance v1

- Visión por imagen, OCR, chat sobre historial (roadmap fase 4).
