# ADR-004 — Offline-first (móvil)

## Estado

Aceptada.

## Contexto

Técnicos en campo con conectividad intermitente.

## Decisión

App Flutter con SQLite local; escritura siempre local; sync por eventos cuando hay red. Servidor es source of truth.

## Consecuencias

- Complejidad de sync y resolución de conflictos.
- UX debe exponer estados de cola claramente.
