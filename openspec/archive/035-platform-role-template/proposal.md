# 035 — Configuración de plantilla global de roles (plataforma)

## Problema

Tras ADR-012, la plantilla rol→permiso vivía solo en código; el dueño de sistema no tenía pantalla para ajustar roles base.

## Objetivo

- Persistir plantilla en `platform_settings` (`role_permission_map`).
- API `GET/PUT /platform/role-permissions` solo para **admin de plataforma** (`PHOENIX_PLATFORM_ADMIN_EMAILS`).
- Tras guardar: `bootstrapAllCompanies()` sincroniza Spatie en todas las empresas.
- UI **Plataforma → Roles y permisos** visible si `user.is_platform_admin`.
- Admin de empresa sigue sin editar plantilla; solo usuarios con rol + extras.

## Seguridad

- Lista de correos en config/env, no rol de empresa.
- Rol `administrator` siempre con todos los permisos (bloqueado en UI).

## Dependencias

- ADR-012, cambio 034.
