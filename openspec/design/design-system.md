# Design system — Phoenix

Implementación: **Tailwind CSS 4** + componentes Vue en `resources/js/components/ui/`.

## Dirección visual (2026)

- **Material Design 3**: jerarquía tipográfica, botones con elevación suave, estados claros (success/warning/danger).
- **Glassmorphism**: paneles `glass-panel` (blur + borde blanco semitransparente) sobre fondo degradado oscuro → claro.
- Tipografía: **Inter** (Google Fonts).

## Tokens (`resources/css/app.css`)

| Token | Uso |
|-------|-----|
| `primary-600` | CTA, ítem de nav activo |
| `glass-panel` | Tarjetas principales |
| `glass-sidebar` | Navegación lateral |
| `nav-item` / `nav-item-active` | Enlaces del shell |

## Componentes UI

| Componente | Uso |
|------------|-----|
| `GlassCard` | Contenedor de contenido |
| `AppButton` | primary / secondary / danger / ghost |
| `StatusBadge` | Estados rutina y factura |
| `PageHeader` | Título + subtítulo de página |
| `AppModal` | Diálogo con cuerpo scrollable y **pie fijo** para acciones (Guardar/Cancelar) |
| `AlertBanner` | info, success, warning, danger |

## Shell

`AppShell.vue`: menú agrupado por flujo (Operación, Catálogos, Diseño, Facturación, Administración).

## Facturación (módulo)

Ver [docs/BILLING.md](../../docs/BILLING.md) y pantallas `/app/billing`, `/app/billing/settings`.

## Modo oscuro

Fase posterior; tokens preparados para variables CSS.
