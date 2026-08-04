# Dominio — AI (Phoenix)

Capa de dominio delimitada. Implementación: [ai-gateway.md](../architecture/ai-gateway.md).

## Agregados (Gateway)

### PromptTemplate

- Nombre, versión, system prompt, user template, modelo, temperatura.

### AIInvocation

- `company_id`, `use_case`, proveedor, tokens, excerpts, `status`, `tool_calls` (JSON).

### AiTool (catálogo interno)

- Contrato de herramienta read-only; ejecución scoped por empresa.

## Casos de uso

| Caso | Entrada | Salida |
|------|---------|--------|
| Grammar correction | Texto técnico | Texto corregido |
| Insights assistant | Pregunta (+ conversation_id / history) | Respuesta + sources + tool_calls + conversation_id + presentation? |
| Vision OCR | Imagen | Texto de placa/etiqueta |
| Report narrative | Servicio | Borrador factual desde campos |

## Políticas

1. Toda IA pasa por AI Gateway.
2. Tools de escritura: **prohibidas** en v2.
3. Respuestas operativas deben citar datos de tools o declarar insuficiencia.
4. Revisión humana en entregables al cliente.

## Fuera de alcance v2

- MCP HTTP/stdio externo
- Detección de fugas/corrosión (fase 4b)
- Caché semántica
