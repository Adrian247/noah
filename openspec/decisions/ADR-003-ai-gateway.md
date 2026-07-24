# ADR-003 — AI Gateway

## Estado

Aceptada.

## Contexto

Corrección gramatical y futuras capacidades IA; múltiples proveedores posibles.

## Decisión

Toda invocación LLM pasa por AI Gateway con prompts versionados en BD, auditoría y adapters por proveedor.

## Consecuencias

- Dominio Maintenance no importa SDKs de OpenAI/Anthropic.
- Costos y límites centralizados.
