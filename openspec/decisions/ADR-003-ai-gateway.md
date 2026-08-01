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

## Extensión 2026-07 (v2)

Tool registry interno + grounding (043). Ver [ai-gateway.md](../architecture/ai-gateway.md).

## Extensión 2026-07 (v2.1)

Motor LLM vía `laravel/ai` (`OperationalAssistant`) bajo el mismo Gateway (044). Tools y RBAC siguen siendo Phoenix.
