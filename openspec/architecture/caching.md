# Caching — Phoenix

## Qué cachear

| Dato | TTL | Invalidación |
|------|-----|--------------|
| FormVersion / ReportTemplate publicados | 1h | Evento `*Published` |
| Catálogos ligeros (listas select) | 15m | Admin update |
| Permisos por usuario+empresa | 5m | Cambio de rol |
| HTML preview reporte | No cachear en prod compartido o clave por usuario |

## Backend

- Redis como store por defecto.
- Cache tags por `company_id` donde Laravel lo permita.

## HTTP

- API: `Cache-Control: private` en recursos sensibles; ETags en listados estables opcional.

## Móvil

- SQLite como cache autoritativa local; no confundir con cache Redis servidor.

## Qué no cachear

- Ejecuciones en edición, borradores de factura, cola sync.
