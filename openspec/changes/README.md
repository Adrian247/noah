# Cambios propuestos (OpenSpec)

Flujo para implementación alineada con `openspec/`:

1. **Propuesta** — carpeta `openspec/changes/<id>-<slug>/` con `proposal.md` y `tasks.md`.
2. **Implementación** — código y tests en el repo; marcar tareas en `tasks.md`.
3. **Archivo** — mover la carpeta a `openspec/archive/<id>-<slug>/` al cerrar.
4. **Git** — commit (y push) por cambio archivado o por hito coherente.

Los archivos en `archive/` son registro histórico; la spec vigente sigue en `vision/`, `domain/`, `design/`, etc.

**Cambios activos:** ninguno (todos archivados en `openspec/archive/`; el último es
[046-predictive-maintenance](../archive/046-predictive-maintenance/)).
