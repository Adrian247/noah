# API — Phoenix

Diseño REST JSON para web y móvil. Implementación Laravel (routes + Sanctum o equivalente).

## Convenciones

| Aspecto | Regla |
|---------|--------|
| Base | `/api/v1` |
| Formato | JSON, UTF-8 |
| Errores | RFC 7807 Problem Details o envelope `{ message, errors, code }` consistente |
| Paginación | `?page=&per_page=` + meta `total` |
| Filtrado | Query params documentados por recurso |
| Idempotencia | Header `Idempotency-Key` en POST críticos (sync, facturación) |

## Contexto de tenant

- Header `X-Company-Id` o claim en token tras selección de empresa.
- Todas las rutas de negocio validan membresía.

## Recursos principales (borrador)

```
GET/POST   /api/v1/routines
GET/PATCH  /api/v1/routines/{id}
POST       /api/v1/routines/{id}/executions
POST       /api/v1/routines/{id}/validate
POST       /api/v1/routines/{id}/reject

GET/POST   /api/v1/assets
GET/POST   /api/v1/catalog/items
GET/POST   /api/v1/inventory/supplies

GET/POST   /api/v1/design/forms
POST       /api/v1/design/forms/{id}/versions/{vid}/publish
GET/POST   /api/v1/design/report-templates
POST       /api/v1/design/report-templates/{id}/preview

GET/POST   /api/v1/billing/invoices
POST       /api/v1/billing/invoices/{id}/issue

POST       /api/v1/sync
POST       /api/v1/files/upload (signed flow)
```

## Autenticación

- `POST /api/v1/auth/login` → token.
- `POST /api/v1/auth/logout`, refresh según estrategia elegida.

## OpenAPI

Generar especificación en fase de código (Scribe o similar) y publicar en `/api/docs` (solo no-prod o protegido).

## Versionado

Ver [versioning.md](versioning.md).
