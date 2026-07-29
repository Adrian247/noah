# Offline — Phoenix (móvil)

ADR: [ADR-004](../decisions/ADR-004-offline-first.md).

## Principios

1. Toda escritura va primero a SQLite.
2. UI confirma éxito local de inmediato.
3. Sync es best-effort en background.

## Datos locales

| Tabla / store | Contenido |
|---------------|-----------|
| `routines` | Asignadas, estado local |
| `executions` | Respuestas draft y final |
| `outbox_events` | Cola de sync |
| `form_schemas` | Versiones publicadas cacheadas |
| `media_pending` | Paths locales pendientes de upload |

## Estados de sync por rutina

`local_only` → `pending_upload` → `synced` → `sync_error`

## Conflictos

- Servidor gana en datos validados; técnico reintenta si rechazado.
- Detalle en [synchronization.md](synchronization.md) y [mobile-sync.md](../architecture/mobile-sync.md).
