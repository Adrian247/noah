# Arquitectura por capas — Noah

Monolito modular Laravel; fronteras lógicas.

```mermaid
flowchart TB
  subgraph presentation [Presentación]
    VUE[Vue SPA]
    FLUT[Flutter]
    API_HTTP[Controllers / API Resources]
  end

  subgraph application [Aplicación]
    SVC[Application Services]
    WF[Workflow Runtime]
    ENG[Forms / Reports Engines]
  end

  subgraph domain [Dominio]
    AGG[Agregados y reglas]
    DE[Domain Events]
  end

  subgraph infrastructure [Infraestructura]
    REPO[Repositories Eloquent]
    ST[Storage Adapter]
    AI[AI Gateway Adapters]
    QUEUE[Queue Jobs]
  end

  VUE --> API_HTTP
  FLUT --> API_HTTP
  API_HTTP --> SVC
  SVC --> AGG
  SVC --> ENG
  SVC --> WF
  AGG --> DE
  DE --> QUEUE
  SVC --> REPO
  ENG --> ST
  WF --> AI
  REPO --> PG[(PostgreSQL)]
```

## Reglas

- Dominio **no** importa SDK de LLM ni S3 directo.
- Efectos secundarios (PDF, email, fiscal) vía **eventos + colas**.
- Motores leen **metadatos** versionados, no tablas por tipo de cliente.

ADR: [ADR-001](../decisions/ADR-001-modular-monolith.md), [ADR-006](../decisions/ADR-006-metadata-driven.md).
