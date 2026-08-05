# Demo multi-tenant (Sandbox + Mein Company + Dom-G)

## Cuenta por defecto (pruebas)

| Email | Empresa | Contraseña |
|-------|---------|------------|
| **`admin@sandbox-demo.com`** | **Sandbox** | **`pyro.2026$`** |

Los tres tenants demo arrancan **vírgenes** en local: usuarios/contraseñas, roles, cliente base y workflow. Sin catálogos, sitios, artículos ni servicios.

> Bajo PHPUnit el seeder carga el playground operativo en Sandbox para las pruebas automatizadas (`DemoRoutineFactory`, flujos de lifecycle, etc.). Forzar playground en otro entorno: `PHOENIX_SEED_SANDBOX_PLAYGROUND=true`.

## Contraseña unificada (fase de pruebas)

Todas las cuentas demo: **`pyro.2026$`**

También aplica a las **cuentas del portal de cliente** asociadas a cada cliente de catálogo (`billing_email` = login). Al crear/editar un cliente activo se provisiona o actualiza esa cuenta con la misma contraseña.

Variables: `PHOENIX_DEMO_PASSWORD`, `PHOENIX_DEMO_ROOT_PASSWORD` (ambas default `pyro.2026$`).

## Root (plataforma)

| Email | Contraseña |
|-------|------------|
| `admin@pyro-systems.com` | `pyro.2026$` |

Administrador de sistema: tenants, algoritmo predictivo, roles globales, **Artículos de sistema** (Mitsubishi L200 + catálogo OEM Epiroc/Sandvik para importar a tenants). Elige empresa en el selector (asunción de tenant).

## Mein Company (tenant virgen)

Solo usuarios/contraseñas y cliente **Interno** (`MEIN-INTERNO`). Sin catálogos, sitios, artículos ni servicios demo.

| Email | Rol |
|-------|-----|
| `emilio.sanchez@mein-company.com` | Administrador |
| `misael.palos@mein-company.com` | Técnico |
| `claudio.rodriguez@mein-company.com` | Supervisión |
| `elena.sanchez@mein-company.com` | Facturación |
| `cliente.portal@mein-company.com` | Portal (Interno) |

## Dom-G (tenant virgen)

Solo usuarios/contraseñas y cliente **Interno** (`DOMG-INTERNO`). Sin catálogos, sitios, artículos ni servicios demo.

| Email | Rol |
|-------|-----|
| `gilberto-dominguez@dom-g.com` | Administrador |
| `technician@dom-g.com` | Técnico |
| `gilberto-sanchez@dom-g.com` | Supervisión |
| `luis-olvera@dom-g.com` | Facturación |
| `cliente.portal@dom-g.com` | Portal (Interno) |

## Sandbox (tenant virgen en local)

Misma política que Mein/Dom-G: usuarios + cliente `SANDBOX-CLI-001` + workflow base.

| Email | Rol |
|-------|-----|
| `admin@sandbox-demo.com` | Administrador |
| `technician@sandbox-demo.com` | Técnico |
| `supervisor@sandbox-demo.com` | Supervisión |
| `billing@sandbox-demo.com` | Facturación |
| `cliente.portal@sandbox-demo.com` | Portal |

Ritual: `docker compose exec app php artisan phoenix:refresh-demo`

## Push móvil (opcional)

Por defecto `PHOENIX_PUSH_DRIVER=log` (traza en logs del worker). Para FCM real: `fcm` + `FCM_PROJECT_ID` + `FCM_CREDENTIALS`. Setup de la app: `mobile/README.md` → Push notifications.
