# Cambio: multi-tenant plataforma (root + clientes administradores)

## Modelo

| Actor | Implementación | Alcance |
|-------|----------------|---------|
| Administrador de sistema (root) | `PHOENIX_PLATFORM_ADMIN_EMAILS` + `is_platform_admin` | Plataforma: tenants, plantilla global de roles, edición de workflows en cualquier empresa |
| Cliente administrador (tenant) | `Company` + rol `administrator` con `TenantAdministratorPermissions` | Operación aislada por `company_id`; sin workflows ni roles de plataforma |
| Cliente del tenant | `Client` + rol `client` (portal) | Sin cambios |

## API

- `GET/POST /api/v1/platform/tenants`
- `PATCH /api/v1/platform/tenants/{company}`
- `POST /api/v1/platform/tenants/{company}/memberships`

## Demo

- `TenantDemoProfile`: Mein Company, Dom-G
- `admin@pyro-systems.com`: root con membresía admin en ambos tenants para cambio de contexto

## Escalabilidad

- Aislamiento por `company_id` (global scope) ya existente; no hay cruce de datos entre tenants.
- Root por lista de correos es adecuado para MVP; fase 2: rol `platform_admin` en BD y SSO.
- Provisionar tenant vía API permite onboarding sin seed; el seed demo sigue siendo idempotente por nombre de empresa.
