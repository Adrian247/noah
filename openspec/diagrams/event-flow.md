# Diagrama — Flujo de eventos (Phoenix)

Flujo típico post-campo hasta factura. Detalle: [domain-events.md](../architecture/domain-events.md).

```mermaid
flowchart TD
  RF[RoutineFinished]
  ES[ExecutionSubmitted]
  GCR[GrammarCorrectionRequested]
  GCC[GrammarCorrectionCompleted]
  PV[PendingValidation]
  RV[RoutineValidated]
  RGR[ReportGenerationRequested]
  RG[ReportGenerated]
  IDC[InvoiceDraftCreated]

  RF --> ES
  ES --> GCR
  GCR --> GCC
  GCC --> PV
  PV -->|Supervisor aprueba| RV
  RV --> RGR
  RGR --> RG
  RV --> IDC
```

Los pasos exactos los define el **Workflow** por tipo de rutina.
