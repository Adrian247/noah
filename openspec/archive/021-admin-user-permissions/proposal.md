# 021 — Administrador gestiona permisos de usuario

## Problema

La propuesta **018** entrega mantenedor de usuarios (rol, activo) y muestra los permisos del rol en **solo lectura**. El administrador necesita **ajustar capacidades por persona** sin inventar un rol nuevo por cada combinación.

Si el usuario sigue viendo módulos en el menú aunque no deba usarlos, la experiencia no coincide con la política de acceso.

## Objetivo

1. El **Administrador** define, **por módulo de la aplicación**, acceso de **lectura** y **escritura** para cada usuario.
2. Solo el **rol `administrator`** gestiona usuarios y esta matriz (API + UI).
3. **Visibilidad del menú**: un módulo solo aparece si tiene **lectura o escritura** activa (resuelto en servidor y reflejado en `/auth/me` → `companies[].modules`).
4. Los permisos técnicos (slugs Spatie) se derivan del catálogo de módulos; el administrador no edita slugs sueltos en la UI.
5. Auditoría de cambios (`membership.module_access_updated`).

## Modelo

### Catálogo de módulos (`NoahModuleCatalog`)

Cada módulo define:

- `id`, `label`, rutas de navegación (`nav_routes`)
- permisos de **lectura** y **escritura** (slugs `NoahPermission`)
- excepciones: `dashboard` siempre visible; `sites` visible por defecto si no hay override

### Overrides por membresía

- Columna `company_memberships.module_access` (JSON): matriz explícita `{ module_id: { read, write } }` guardada al editar usuario.
- Si hay override para un módulo con `read: false` y `write: false`, se **retiran** los permisos de ese módulo aunque el rol los incluya (el menú queda oculto).
- Si no hay override para un módulo, aplican rol + permisos directos Spatie (comportamiento heredado).

### Resolución

- `permissionsForUser()` aplica overrides antes de evaluar middleware.
- `modulesForMembership()` devuelve `{ read, write, visible }` por módulo para la UI.
- Escritura implica lectura en la UI al marcar el checkbox.

## API

Middleware: `auth:sanctum`, `company`, `company.role:administrator` en `/company/users` y `/company/roles`.

| Método | Ruta | Cuerpo / respuesta |
|--------|------|---------------------|
| GET | `/company/users` | `modules`, `module_access` por usuario |
| PUT | `/company/users/{user}` | `modules: Record<id, {read, write}>` (todos los módulos editables) |
| GET | `/company/roles` | `modules_catalog` |
| GET | `/auth/me` | `companies[].modules` para menú y guards |

## UI

- **Administración → Usuarios**: tabla por módulo con **Lectura** y **Escritura**.
- Escritura en API y pantallas se valida con `company.module:{id},write` (la matriz manda, no solo el slug Spatie).
- Equipos e insumos: lectura = `catalog.view`; escritura = `catalog.manage` (evita que lectura otorgue alta/edición).
- **AppShell**: ítems de menú filtrados con `companies[].modules[*].visible`.
- **Router**: meta `moduleId` en rutas sensibles (p. ej. clientes).

## Fuera de alcance

- Roles personalizados por empresa.
- Permisos negados granulares dentro de un mismo slug.
- Invitaciones por correo.

## Criterios de aceptación

1. Solo administrador accede al mantenedor (403 para otros roles).
2. Técnico con módulo Clientes sin lectura ni escritura: no ve el ítem en menú, `GET /clients` → 403.
3. Lectura sin escritura: ve listados; botones de alta/edición deshabilitados u ocultos según `write`.
4. Tras guardar, el usuario afectado ve el menú actualizado al volver a cargar sesión (`/auth/me` o re-login).

## Dependencias

- 018 (Spatie RBAC, `CompanyAuthorizationService`).
