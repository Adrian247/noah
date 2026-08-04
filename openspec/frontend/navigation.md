# Navegación — Phoenix (web)

Alineada con [information-architecture.md](../design/information-architecture.md).

## Rutas Vue Router (borrador)

| Ruta | Nombre | Permiso |
|------|--------|---------|
| `/login` | login | público |
| `/app/dashboard` | dashboard | autenticado |
| `/app/routines` | routines.index | `routines.view` |
| `/app/routines/:id` | routines.show | `routines.view` |
| `/app/assets` | assets.index | `assets.view` |
| `/app/catalog/equipment` | catalog.equipment | `catalog.manage` |
| `/app/catalog/supplies` | catalog.supplies | `catalog.manage` |
| `/app/design/routine-types` | design.routine-types | `design.forms` |
| `/app/design/forms/:id` | design.forms.edit | `design.forms` |
| `/app/design/reports/:id` | design.reports.edit | `design.reports` |
| `/app/design/workflows/:id` | design.workflows.edit | `design.workflows` |
| `/app/billing/drafts` | billing.drafts | `billing.draft` |
| `/app/billing/invoices` | billing.invoices | `billing.issue` |
| `/app/settings/company` | settings.company | admin |
| `/app/settings/users` | settings.users | admin |
| `/app/audit` | audit.index | `audit.view` |

## Guards

- `auth` → todas `/app/*`
- `permission` → meta en ruta
- `company` → redirigir a selector si falta contexto

## Breadcrumbs

Generados desde meta `breadcrumb: ['Operación', 'Servicios']`.
