# ADR-012 — Portal cliente e identidad (propuesta)

## Estado

Propuesto (vinculado a cambio **032**).

## Contexto

Los clientes comerciales (`Client`) hoy son catálogo para facturación, sin usuarios propios. Se requiere acceso **solo lectura** a facturas autorizadas y rutinas de equipos vinculados por número de serie, sin mezclar datos entre clientes ni exponer el backoffice de la empresa.

## Decisión (propuesta)

1. Extender membresía con `client_id` opcional; si está presente, el usuario es **usuario portal** de ese cliente en la empresa.
2. Rol `client` en `MembershipRole` (nombre UI: «Cliente») con permisos mínimos vía Spatie (`portal.invoices.view`, `portal.routines.view`, `portal.invoices.download`).
3. API dedicada bajo `/api/v1/portal/*` con autorización que **siempre** filtra por `membership.client_id`.
4. Facturas visibles solo si `client_portal_visible` y emitidas; rutinas solo si el activo tiene asignación activa `asset_client_assignments` al mismo cliente.

## Consecuencias

- Un usuario puede ser técnico en empresa A y cliente en empresa B (membresías separadas).
- No reutilizar módulos `billing` / `routines` del admin en portal sin duplicar endpoints (evitar fugas por olvido de scope).
- Login y Sanctum sin cambio; `GET /auth/me` debe incluir `client_id` por membresía activa.

## Alternativas

- Magic link sin usuario: descartado para v1 (sin historial de sesión ni revocación).
- Tabla `client_users` separada de `users`: posible en v2 si se requiere invitación masiva.
