# ADR-011 — Autorización RBAC multi-empresa (Spatie Permission)

## Estado

Aceptado (2026-07).

## Contexto

Noah es multi-tenant por **empresa** (`X-Company-Id`). Los usuarios tienen membresías con un rol operativo (administrador, supervisor, técnico, …). Se requiere un catálogo de **permisos** estable y un mantenedor de usuarios sin duplicar lógica en cada controlador.

## Decisión

- Usar **[spatie/laravel-permission](https://github.com/spatie/laravel-permission)** con **teams** habilitado.
- `team_foreign_key` = **`company_id`** en tablas de roles y asignaciones.
- Permisos globales (mismo slug en todas las empresas); **roles por empresa** (filas en `roles` con `company_id`).
- La columna `company_memberships.role` (enum) se mantiene en v1 como fuente visible para la UI y se sincroniza con Spatie vía `CompanyAuthorizationService` (dual-write).
- Middleware `company.permission` evalúa permisos con el team de `CurrentCompany`.
- Resolver de team: `App\Support\CompanyTeamResolver` (lee `CurrentCompany` en requests API con contexto de empresa).

## Consecuencias

- Comando idempotente `php artisan noah:bootstrap-permissions` para catálogo, roles por empresa y asignaciones.
- `GET /auth/me` y login devuelven `permissions[]` por empresa para la UI.
- Roles personalizados por cliente: posible en fase 2 sin cambiar el modelo base.
- Dependencia externa acotada al bounded context **Identity**.

## Alternativas consideradas

- Solo enum + middleware: insuficiente para mantenedor y matriz de permisos.
- RBAC tablas propias: más control, más código y mantenimiento que Spatie para el mismo resultado.
