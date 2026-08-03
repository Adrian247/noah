# Demo multi-tenant (Sandbox + Mein Company + Dom-G)

## Cuenta por defecto (pruebas)

| Email | Empresa | Contraseña |
|-------|---------|------------|
| **`admin@sandbox-demo.com`** | **Sandbox** (tenant virgen) | **`pyro.2026$`** |

Sin catálogos, rutinas ni activos precargados — ideal para onboarding y pruebas desde cero.

## Contraseña unificada (fase de pruebas)

Todas las cuentas demo: **`pyro.2026$`**

Variables: `PHOENIX_DEMO_PASSWORD`, `PHOENIX_DEMO_ROOT_PASSWORD` (ambas default `pyro.2026$`).

## Root (plataforma)

| Email | Contraseña |
|-------|------------|
| `admin@pyro-systems.com` | `pyro.2026$` |

Administrador de sistema: tenants, algoritmo predictivo, roles globales. Elige empresa en el selector (asunción de tenant).

## Mein Company

Clientes demo: **Mina Velardeña**, **Presidencia Municipal Sombrerete**, **Interno**.

| Email | Rol |
|-------|-----|
| `emilio.sanchez@mein-company.com` | Administrador |
| `misael.palos@mein-company.com` | Técnico |
| `claudio.rodriguez@mein-company.com` | Supervisión |
| `elena.sanchez@mein-company.com` | Facturación |
| `cliente.portal@mein-company.com` | Portal (Mina Velardeña) |

## Dom-G

Clientes demo: **Grupo México**, **Interno**.

| Email | Rol |
|-------|-----|
| `gilberto-dominguez@dom-g.com` | Administrador |
| `technician@dom-g.com` | Técnico |
| `gilberto-sanchez@dom-g.com` | Supervisión |
| `luis-olvera@dom-g.com` | Facturación |
| `cliente.portal@dom-g.com` | Portal (Grupo México) |

## Sandbox (tenant virgen)

Misma fila que **cuenta por defecto** arriba. Empresa demo sin datos operativos — equivalente a un alta desde **Clientes de plataforma**.

| Email | Rol |
|-------|-----|
| `admin@sandbox-demo.com` | Administrador |

Ritual: `docker compose exec app php artisan phoenix:refresh-demo`

## Push móvil (opcional)

Por defecto `PHOENIX_PUSH_DRIVER=log` (traza en logs del worker). Para FCM real: `fcm` + `FCM_PROJECT_ID` + `FCM_CREDENTIALS`. Setup de la app: `mobile/README.md` → Push notifications.
