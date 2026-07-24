# Diagramas — Noah

Índice de diagramas **documentales** (Mermaid). Sirven para alinear objetivo, arquitectura, diseño y flujos sin leer decenas de páginas.

## Cómo usarlos

- **Producto / negocio:** contexto del sistema y flujo punta a punta.
- **Arquitectura:** contenedores, capas, módulos, eventos, sync, reportes.
- **Diseño:** configuración admin y experiencia de validación.
- **Futuro:** IA y visión (fase 4).

## Índice

| Diagrama | Archivo | Audiencia |
|----------|---------|-----------|
| Contexto del sistema (C4 nivel 1) | [system-context.md](system-context.md) | Todos |
| Contenedores / despliegue lógico | [containers.md](containers.md) | Arquitectura, DevOps |
| Capas del monolito | [layered-architecture.md](layered-architecture.md) | Backend |
| Mapa de módulos | [module-map.md](module-map.md) | Desarrollo |
| Flujo negocio end-to-end | [end-to-end-flow.md](end-to-end-flow.md) | Producto, QA |
| Flujo validación y facturación | [validation-billing-flow.md](validation-billing-flow.md) | Supervisor, facturación |
| Eventos de dominio | [event-flow.md](event-flow.md) | Backend |
| Sync móvil | [sync-flow.md](sync-flow.md) | Móvil, backend |
| Motor de reportes | [report-engine.md](report-engine.md) | Reportes, diseño |
| Estudio de configuración (diseño) | [design-configurator-flow.md](design-configurator-flow.md) | UX, admin |
| Metadatos: tres motores | [metadata-engines.md](metadata-engines.md) | Arquitectura |
| Roadmap visual | [roadmap-phases.md](roadmap-phases.md) | Planificación |
| Capacidades futuras IA/visión | [future-ai-vision.md](future-ai-vision.md) | Producto, IA |

## Fuente de verdad textual

Los diagramas **resumen** documentos en `openspec/`. Si hay conflicto, prevalece el markdown del dominio/arquitectura correspondiente.
