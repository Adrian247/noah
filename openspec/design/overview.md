# Diseño — Phoenix

Esta carpeta describe **experiencia de usuario**, **arquitectura de información** y **patrones de interfaz** para la fase de diseño (sin implementación UI aún).

## Objetivos de diseño

1. Que un administrador configure tipos de rutina y reportes **sin sensación de “desarrollo”**.
2. Que el técnico en móvil complete rutinas **en pocos toques**, con feedback claro de estado offline/sync.
3. Que supervisor y facturación tengan **colas de trabajo** claras (pendientes de validar / facturar).

## Documentos

| Archivo | Contenido |
|---------|-----------|
| [information-architecture.md](information-architecture.md) | Módulos de navegación web |
| [personas-and-journeys.md](personas-and-journeys.md) | Personas y recorridos |
| [report-designer.md](report-designer.md) | Diseñador de reportes (layout Canva) |
| [form-designer.md](form-designer.md) | Diseñador de formularios |
| [mobile-field-app.md](mobile-field-app.md) | App de campo |
| [design-system.md](design-system.md) | Tokens, componentes base, Tailwind |

## Diagramas de diseño y flujos

Ver [../diagrams/README.md](../diagrams/README.md) — en especial [design-configurator-flow.md](../diagrams/design-configurator-flow.md), [validation-billing-flow.md](../diagrams/validation-billing-flow.md), [end-to-end-flow.md](../diagrams/end-to-end-flow.md).

## Principios UX (Phoenix)

- **Claridad operativa** sobre decoración; dashboards en fases posteriores.
- **Estados visibles**: borrador, en ejecución, pendiente sync, pendiente validación, facturado.
- **Diseñadores a pantalla completa** para formularios y reportes (modo “estudio”).
- Accesibilidad: contraste WCAG AA mínimo en admin (ver design-system).

## Marca

Nombre producto: **Phoenix**. Identidad visual (logo, paleta) — pendiente; el design system usa placeholders hasta definir marca.
