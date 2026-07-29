# Usuarios objetivo — Phoenix

Complementa personas en [design/personas-and-journeys.md](../design/personas-and-journeys.md).

## Roles del sistema

| Rol | Objetivo en Phoenix | Frecuencia |
|-----|------------------|------------|
| **Administrador** | Configurar empresa, catálogos, diseño, permisos | Diaria |
| **Supervisor** | Validar ejecuciones, revisar costos y evidencias | Diaria |
| **Técnico** | Ejecutar rutinas, capturar evidencias | Diaria (móvil fase 2) |
| **Facturación** | Emitir facturas desde trabajos validados | Semanal |
| **Cliente final** (futuro) | Consultar reportes y historial de activo | Ocasional |
| **Auditor** | Revisar trazabilidad sin editar | Ocasional |

## Permisos (v1 — conjunto mínimo)

| Permiso | Roles típicos |
|---------|---------------|
| `catalog.manage` | Administrador |
| `assets.manage` | Administrador |
| `design.forms` / `design.reports` / `design.workflows` | Administrador |
| `routines.assign` | Administrador, Supervisor |
| `routines.execute` | Técnico |
| `routines.validate` | Supervisor |
| `costs.view` | Supervisor, Administrador |
| `billing.draft` / `billing.issue` | Facturación |
| `audit.view` | Administrador, Auditor |
| `ai.invoke` | Sistema (workflow); humanos solo configuran prompts |

Los nombres finales se fijan en implementación; mantener alineación con el glosario.

## Anti-personas (no optimizar en v1)

- Usuario final que solo necesita un PDF estático sin catálogo ni historial.
- Organización sin supervisión (solo captura sin validación) — soportable pero no caso de diseño principal.
