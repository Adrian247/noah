# ADR-002 — PostgreSQL

## Estado

Aceptada.

## Contexto

Catálogos relacionales, historial, costos y plantillas JSON (formularios, reportes).

## Decisión

PostgreSQL como BD principal; JSONB para definiciones versionadas.

## Alternativas

- MySQL — viable pero JSONB menos ergonómico.
- MongoDB — rechazado para núcleo transaccional.

## Consecuencias

- Migraciones Laravel estándar.
- Índices GIN en JSONB donde haya consultas por clave.
