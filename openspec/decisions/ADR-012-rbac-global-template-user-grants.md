# ADR-012 — Plantilla global de roles y concesiones por usuario

## Estado

Aceptado (2026-07).

## Contexto

Los administradores de empresa deben gestionar acceso sin redefinir qué significa cada rol en cada cliente. Se necesita escalabilidad operativa y un modelo fácil de auditar.

## Decisión

- La **matriz rol → permiso** es **global** (código + bootstrap); idéntica en todas las empresas.
- El **admin de empresa** solo: asigna `MembershipRole` y opcionalmente **permisos directos** Spatie (`extra_permissions`) aditivos respecto al rol.
- La visibilidad de **módulos** en UI es **derivada** de permisos efectivos (`NoahModuleCatalog`); no se persiste override por módulo en v2 del mantenedor (columna `module_access` obsoleta).
- Cambios a la plantilla global: equipo de plataforma (deploy / `noah:bootstrap-permissions`), no UI tenant.

## Consecuencias

- Menos combinaciones por cliente; soporte y pruebas se centran en roles estándar + extras documentados.
- La UI de usuarios expone slugs agrupados, no lectura/escritura manual por módulo.
- Ver cambio [034](../changes/034-rbac-role-plus-grants/proposal.md).

## Relación

- Complementa [ADR-011](ADR-011-rbac-spatie-teams.md) (Spatie + teams).
