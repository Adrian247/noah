# Mapa de contexto — Noah

```mermaid
flowchart TB
  subgraph noah [Noah Platform]
    ID[Identity]
    CO[Companies]
    AS[Assets]
    CA[Catalogs]
    IN[Inventory]
    MA[Maintenance]
    DF[Dynamic Forms]
    DR[Dynamic Reports]
    WF[Workflow]
    BI[Billing]
    AI[AI Gateway]
    SY[Synchronization]
    ST[Storage]
    AU[Audit]
    NT[Notifications]
  end

  MA --> DF
  MA --> DR
  MA --> WF
  MA --> AS
  MA --> IN
  WF --> AI
  WF --> BI
  WF --> DR
  WF --> NT
  SY --> MA
  SY --> ST
  DR --> ST
  MA --> ST
  ID --> CO
  CO --> AS
  CA --> IN
  AU -.-> MA
  AU -.-> BI
```

**Lectura:** Maintenance orquesta la operación; consume Forms y Reports; Workflow coordina IA, Billing y Notifications. Sync y Storage son transversales.

## Integraciones externas (futuro)

```mermaid
flowchart LR
  Noah[Noah]
  PAC[Proveedor fiscal PAC]
  Email[SMTP / Email API]
  LLM[Proveedores LLM]

  Noah --> PAC
  Noah --> Email
  AI[AI Gateway] --> LLM
```

Ninguna integración externa es obligatoria para el MVP web excepto almacenamiento y (opcional) SMTP.
