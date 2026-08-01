# 043 — AI Gateway v2: tool registry y grounding

**Issue:** [TXA-2749](https://txa-labs.youtrack.cloud/issue/TXA-2749)

## Contexto

Phoenix ya tenía ADR-003 (AI Gateway transversal) y un MVP (gramática, OCR, asistente RAG estático). La experiencia previa con MCP + gateway de asistente demuestra que la confiabilidad viene de **herramientas de dominio de solo lectura** + orquestación LLM, no de contexto pegado al azar.

Referencia (solo patrón, no copiar stack): `/Users/aolvera/development/mcp`, `/Users/aolvera/development/ai-assistant`.

## Decisión

Adaptar a Phoenix un **tool registry interno** en Laravel (sin servidor MCP Python aparte en v2):

1. Toda invocación LLM/visión pasa por `AiGateway`.
2. Adapter de proveedor (`OpenAi` / `Local`).
3. Tools read-only con scope `company_id`: rutinas, auditoría, activos, insumos.
4. Asistente Insights: bucle tool-calling cuando hay proveedor LLM; en modo local ejecuta tools por heurística y formatea desde datos reales (grounding).
5. Auditoría en `AiInvocation` con `tool_calls` JSON.
6. OCR y narrativa consolidan logging/cuotas vía Gateway.

## Fuera de alcance v2

- Servidor MCP HTTP/stdio externo
- Tools de escritura
- Caché semántica Redis
- Adapters Anthropic/Ollama (contrato listo; implementación posterior)

## Criterios de aceptación

- Asistente responde con `sources` derivadas de tools
- Sin OpenAI: respuestas aún grounded en tools (no inventan IDs)
- OCR/narrativa no llaman OpenAI fuera del Gateway
- Tests feature/unit verdes
