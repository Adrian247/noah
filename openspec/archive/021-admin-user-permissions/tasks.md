# Tareas 021 — Permisos de usuario (solo administrador)

## Propuesta

- [x] `proposal.md` — módulos lectura/escritura y visibilidad de menú

## Backend

- [x] `PhoenixModuleCatalog` + `module_access` en membresía
- [x] Resolución `modules` en `/auth/me` y permisos efectivos
- [x] `PUT /company/users` con `modules`
- [x] Tests (ocultar módulo clientes, etc.)

## Frontend

- [x] `CompanyUsersPage` matriz por módulo
- [x] `useModuleAccess` + menú filtrado en `AppShell`
- [x] Guard `moduleId` en rutas (clientes)

## Cierre

- [x] `docs/PRUEBAS_MANUALES.md`
- [ ] Archivar al cerrar hito
