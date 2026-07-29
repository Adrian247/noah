# 034 — RBAC global: rol + permisos adicionales por usuario

## Problema

La UI de usuarios (021) editaba **acceso por módulo** (`module_access`), lo que parecía ACL por persona y desalineaba el modelo acordado en [ADR-011](../../decisions/ADR-011-rbac-spatie-teams.md): plantilla de roles **global** (misma para todas las empresas) y elevaciones **puntuales** por usuario.

## Objetivo

1. **Plantilla de roles** definida en plataforma (`CompanyAuthorizationService::rolePermissionMap()` + bootstrap Spatie); ningún admin de empresa puede redefinir permisos de un rol.
2. **Admin de empresa** asigna **rol** y, opcionalmente, **permisos adicionales** (`extra_permissions`, slugs `PhoenixPermission` no incluidos en el rol).
3. **Menú y middleware** derivan de permisos efectivos: `rol ∪ extras` (sin matriz `module_access`).
4. Deprecar escritura de `modules` / `module_access` en API; columna JSON se ignora en resolución (reservada para migración futura).

## Modelo

```
permisos_efectivos = permisos(rol_global) ∪ permisos_directos_usuario
modules[*] = proyección UX desde permisos_efectivos (PhoenixModuleCatalog)
```

- Cambios a la plantilla global: **despliegue / admin de sistema** (código + `phoenix:bootstrap-permissions`), no UI tenant.
- Auditoría: `membership.permissions_updated` al guardar extras.

## API

| Cambio | Detalle |
|--------|---------|
| `PUT/POST /company/users` | `extra_permissions` (opcional); `modules` **prohibido** (422) |
| `GET /company/roles` | `permission_groups` para UI de concesiones |
| `GET /company/users` | `role_permissions`, `extra_permissions`, `effective_permissions`, `modules` (derivado) |

## UI

- **Administración → Usuarios**: rol + lista de permisos adicionales agrupados; permisos del rol en solo lectura.
- Sin matriz lectura/escritura por módulo.

## Fuera de alcance

- Consola de admin de sistema para editar plantilla sin deploy.
- Roles personalizados por empresa.
- Permisos negativos (deny).

## Criterios de aceptación

1. Técnico con `extra_permissions: ['clients.view']` ve clientes y `GET /clients` → 200.
2. Técnico con solo `assets.view` (extra) puede listar activos pero no crear.
3. `modules` en body de usuario → 422 con mensaje claro.
4. Menú coherente con `/auth/me` tras guardar extras.

## Dependencias

- ADR-011, archivo 021 (matriz por módulo sustituida en producto).
