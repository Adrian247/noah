# Tres motores de metadatos — Phoenix

Núcleo configurable de Phoenix.

```mermaid
flowchart TB
  RT[Tipo de servicio]

  RT --> F[Dynamic Forms Engine]
  RT --> R[Dynamic Report Engine]
  RT --> W[Workflow Engine]

  F -->|FormVersion JSON| CAP[Captura web / móvil]
  R -->|Template JSON| PDF[PDF generado]
  W -->|Grafo de flujo| AUTO[IA PDF Factura Notificaciones]

  CAP --> EXE[Ejecución]
  EXE --> AUTO
  AUTO --> PDF
```

| Motor | Pregunta | Artefacto |
|-------|----------|-----------|
| Forms | ¿Qué datos? | FormVersion |
| Reports | ¿Cómo se ve el entregable? | ReportTemplateVersion |
| Workflow | ¿Qué ocurre después? | WorkflowDefinition |

Sin estos tres, cada cliente nuevo implicaría código nuevo — contrario a [ADR-006](../decisions/ADR-006-metadata-driven.md).
