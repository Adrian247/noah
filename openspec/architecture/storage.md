# Storage — Noah

## Principio

Los binarios (fotos, PDFs generados, firmas, logos) **no** se almacenan en PostgreSQL.

## Metadatos en BD

- `id`, `company_id`, `path` o `key`, `mime`, `size`, `hash`, `uploaded_at`, `uploaded_by`, `purpose` (evidence, logo, report, …).

## Backends

Interfaz única (`StorageEngine` / filesystem disk Laravel):

- Desarrollo: local o MinIO
- Producción: S3-compatible o Azure Blob

## URLs

- Descarga mediante URLs firmadas de corta duración.
- El móvil sube con credenciales temporales o endpoint dedicado post-sync.

## Retención

Políticas por empresa (futuro); diseño inicial: soft-delete + job de purga configurable.
