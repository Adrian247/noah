# Backups — Noah

## PostgreSQL

- Backup diario completo + WAL si proveedor lo soporta.
- Retención: 30 días mínimo producción.
- Prueba de restore trimestral.

## Object storage

- Versionado de bucket opcional.
- Lifecycle: archivar evidencias antiguas según política empresa.

## Configuración

- Export de definiciones (forms, reports, workflows) recomendado antes de cambios masivos — script futuro `noah:export-tenant`.

## Disaster recovery

- RPO objetivo: 24h (piloto); RTO: 4h.
- Documentar runbook en fase operaciones.
