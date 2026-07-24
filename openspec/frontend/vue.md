# Frontend — Vue (Noah)

## Stack

- Vue 3 (Composition API, `<script setup>`)
- Vite
- Pinia (estado global: auth, company context, UI)
- Vue Router
- Tailwind CSS + design system en [design-system.md](../design/design-system.md)

## Estructura de carpetas (propuesta)

```
resources/js/
  app.ts
  router/
  stores/
  layouts/          # AppShell
  pages/            # por área IA
  components/
    ui/             # primitivos
    domain/         # RoutineCard, AssetPicker, ...
  features/
    design/         # form & report designers
    maintenance/
    billing/
  api/              # clientes HTTP tipados
  composables/
```

## Comunicación con API

- Cliente HTTP con interceptors: token, `X-Company-Id`, manejo 401/403.
- Tipos TypeScript generados desde OpenAPI cuando exista.

## Diseñadores

- `features/design/report-designer`: canvas, paleta, propiedades (ver [report-designer.md](../design/report-designer.md)).
- `features/design/form-builder`: análogo para formularios.

## Laravel

- Inertia opcional; por defecto **SPA desacoplada** servida desde `public/` o mismo dominio con fallback route.
- CSRF solo si sesión cookie; con token API, Sanctum SPA o pure API.

## Calidad

- ESLint + Prettier; Vitest para composables y stores críticos.

## Accesibilidad

- Ver [accessibility.md](accessibility.md).
