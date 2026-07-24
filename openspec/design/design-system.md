# Design system — Noah (borrador)

Implementación prevista: **Tailwind CSS** + componentes Vue 3 (Headless UI o Radix-vue según preferencia en código).

## Marca (pendiente)

- Nombre: **Noah**
- Logo y paleta: TBD — usar tokens neutros hasta definir identidad.

## Tokens propuestos

| Token | Uso |
|-------|-----|
| `primary` | Acciones principales, enlaces |
| `surface` | Fondos de tarjetas |
| `border` | Divisores |
| `success` / `warning` / `danger` | Estados de rutina y sync |
| `text-muted` | Metadatos, ayudas |

## Tipografía

- UI: sans moderna (Inter, DM Sans o system stack).
- Reportes PDF: configurable por plantilla (no fijada por design system admin).

## Componentes base (admin)

- `AppShell` — sidebar + topbar + breadcrumb
- `DataTable` — filtros, paginación server-side
- `StatusBadge` — estados de rutina/sync
- `FormField` — wrapper accesible label + error
- `DesignerCanvas` — lienzo reportes/formularios
- `ConfirmDialog`, `Toast`, `EmptyState`

## Densidad

- Admin: densidad **comfortable** (no ultra-compacta) para usuarios de oficina variados.
- Móvil: targets táctiles mínimo 44px.

## Modo oscuro

Opcional fase 2; diseñar tokens con variables CSS desde inicio para facilitar.

## Iconografía

Lucide o Heroicons; consistencia en sidebar y estados.
