# Contenedores — Phoenix

Vista C4 **nivel 2**: aplicaciones y servicios en runtime.

```mermaid
flowchart TB
  subgraph clients [Clientes]
    WEB[Web SPA Vue]
    MOB[App Flutter]
  end

  subgraph phoenix [Phoenix - despliegue]
    NGX[Nginx]
    API[Laravel API]
    WRK[Workers Horizon]
    REB[Reverb - futuro tiempo real]
  end

  subgraph data [Infraestructura datos]
    PG[(PostgreSQL)]
    RD[(Redis)]
    S3[(Object Storage)]
  end

  WEB -->|HTTPS JSON| NGX
  MOB -->|HTTPS JSON sync| NGX
  NGX --> API
  API --> PG
  API --> RD
  API --> S3
  WRK --> RD
  WRK --> PG
  WRK --> S3
  WRK -->|IA PDF email| EXT[APIs externas]
  API -.-> REB
  WEB -.-> REB
```

## Responsabilidades

| Contenedor | Rol |
|------------|-----|
| Web SPA | Admin, diseño, validación, facturación |
| Flutter | Captura offline, sync |
| Laravel API | Dominio, motores, auth, REST |
| Workers | PDF, IA, notificaciones, jobs pesados |
| PostgreSQL | Transaccional + JSONB metadatos |
| Redis | Colas, cache, sesiones |
| Object storage | Fotos, PDFs, logos |

Detalle: [architecture.md](../architecture/architecture.md), [docker.md](../infrastructure/docker.md).
