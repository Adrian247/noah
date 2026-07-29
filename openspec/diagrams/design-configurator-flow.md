# Flujo estudio de configuración — Phoenix

Cómo el **administrador** arma un tipo de rutina (diseño UX).

```mermaid
flowchart LR
  subgraph studio [Modo estudio - pantalla completa]
    F[Form Designer]
    R[Report Designer]
    W[Workflow Editor]
  end

  subgraph link [Ensamble]
    RT[Tipo de rutina]
    PUB[Publicar versión]
  end

  F --> RT
  R --> RT
  W --> RT
  RT --> PUB
  PUB --> MOB[Sync definiciones a móvil]
  PUB --> WEB[Disponible nuevas rutinas web]
```

## Orden recomendado en UI (onboarding)

1. Catálogo de activos/insumos mínimo.
2. Formulario (qué se captura).
3. Plantilla de reporte (cómo se imprime).
4. Workflow (qué pasa al terminar y al validar).
5. Tipo de rutina + prueba con rutina de ejemplo.

Wireframes: [form-designer.md](../design/form-designer.md), [report-designer.md](../design/report-designer.md).
