# Propuesta 001 — UI catálogos y activos (Fase 1)

## Objetivo

Exponer en la SPA las APIs existentes de **equipos de catálogo**, **insumos** y **activos por sitio**, con navegación según [information-architecture.md](../../design/information-architecture.md).

## Alcance

- Rutas `/app/catalog/items`, `/app/catalog/supplies`, `/app/assets`.
- Listado y alta mínima (formulario en página).
- Enlaces en `AppShell` bajo sección Catálogos / Operación.

## Fuera de alcance

- CRUD completo (editar/eliminar), diseñadores, auditoría.

## Criterios de aceptación

- Admin autenticado puede listar y crear ítems en las tres pantallas.
- Crear activo requiere sitio (desde `GET /sites`).

## Referencias

- [domain/catalogs.md](../../domain/catalogs.md), [domain/assets.md](../../domain/assets.md), [domain/inventory.md](../../domain/inventory.md)
