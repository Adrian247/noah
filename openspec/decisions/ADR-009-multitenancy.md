# ADR-009 — Multitenancy

## Estado

Aceptada (modelo desde fase 1; SaaS comercial en fase posterior).

## Contexto

Phoenix debe soportar múltiples empresas sin mezclar datos.

## Decisión

`company_id` en entidades de negocio; scopes globales en capa de aplicación; preparar subdominios o selector de empresa en UI.

## Alternativas

- Base de datos por tenant — posponer hasta escala extrema.

## Consecuencias

- Tests siempre con contexto de empresa.
- Índices compuestos `(company_id, ...)`.
