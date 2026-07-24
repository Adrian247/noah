# ADR-007 — Arquitectura orientada a eventos

## Estado

Aceptada.

## Contexto

Desacoplar validación, PDF, facturación y notificaciones.

## Decisión

Eventos de dominio + listeners en cola; ver [domain-events.md](../architecture/domain-events.md).

## Consecuencias

- Debugging requiere trazabilidad de eventos.
- Idempotencia en consumidores.
