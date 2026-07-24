# ADR-008 — Object storage

## Estado

Aceptada.

## Contexto

Fotos de evidencia, PDFs, logos; volumen creciente.

## Decisión

Binarios en S3/MinIO; PostgreSQL solo metadatos. Ver [storage.md](../architecture/storage.md).

## Consecuencias

- Configuración de buckets y políticas IAM.
- URLs firmadas para descarga.
