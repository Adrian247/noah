# Demo multi-tenant (Mein Company + Dom-G)

## Root (plataforma)

| Email | Contraseña |
|-------|------------|
| `admin@pyro-systems.com` | `pyro.2026$` |

Variables: `PHOENIX_DEMO_ROOT_PASSWORD`, `PHOENIX_PLATFORM_ADMIN_EMAILS`.

## Cuentas tenant (Mein, Dom-G, etc.)

Contraseña común: **`phoenix.2026$`** (o `PHOENIX_DEMO_PASSWORD`).

## Mein Company

| Email | Rol |
|-------|-----|
| `emilio.sanchez@mein-company.com` | Administrador |
| `misael.palos@mein-company.com` | Técnico |
| `claudio.rodriguez@mein-company.com` | Supervisión |
| `elena.sanchez@mein-company.com` | Facturación |

## Dom-G

| Email | Rol |
|-------|-----|
| `gilberto-dominguez@dom-g.com` | Administrador |
| `technician@dom-g.com` | Técnico |
| `gilberto-sanchez@dom-g.com` | Supervisión |
| `luis-olvera@dom-g.com` | Facturación |

El root **no** tiene membresía en los tenants: elige empresa en el selector (marcada «plataforma») y cada cambio registra auditoría `platform.tenant_assumed`.

Ritual: `docker compose exec app php artisan phoenix:refresh-demo`
