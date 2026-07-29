# Tareas 018 — Usuarios, roles y permisos

## Fase 0 — Preparación

- [x] ADR-011: Spatie Permission con `company_id` como team
- [x] Seeder catálogo de permisos (slugs de target-users + `company.users.manage`)
- [x] Seeder roles plantilla y asignación permisos por rol
- [x] Comando `phoenix:bootstrap-permissions` (enum → Spatie) para datos existentes

## Fase 1 — Infraestructura Identity

- [x] Instalar y configurar `spatie/laravel-permission` (teams)
- [x] `CompanyAuthorizationService` + middleware `company.permission`
- [x] Extender `GET /auth/me` y login con `permissions[]` por empresa
- [x] Dual-write al cambiar rol (enum + Spatie)

## Fase 2 — API mantenedor

- [x] Ampliar `GET /company/users` (is_active, role_label)
- [x] `PUT /company/users/{user}` — rol y activo
- [x] `POST /company/users` — alta por email
- [x] `GET /company/roles` — roles y permisos (lectura)
- [x] Auditoría en mutaciones
- [x] Tests Feature (admin OK, supervisor 403)

## Fase 3 — UI

- [x] Rediseñar `CompanyUsersPage` (tabla, filtros, edición)
- [x] Guard de ruta + ítem menú por permiso
- [x] Checklist permisos efectivos (read-only v1)
- [x] Pruebas manuales en `docs/PRUEBAS_MANUALES.md`

## Fase 4 — Migración autorización

- [ ] Sustituir `company.role:*` por permisos en rutas (por módulo)
- [ ] Eliminar comprobaciones duplicadas en controladores donde haya policy
- [ ] Deprecar columna `company_memberships.role` (migración final)

## Cierre

- [ ] Actualizar `openspec/domain/identity.md`
- [ ] Archivar `openspec/changes/018-company-users-rbac/` → `openspec/archive/`
