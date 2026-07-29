# Contexto del sistema — Phoenix

Vista C4 **nivel 1**: actores y sistema Phoenix.

```mermaid
flowchart TB
  subgraph actors [Actores]
    ADM[Administrador]
    SUP[Supervisor]
    TEC[Técnico de campo]
    FAC[Facturación]
    CLI[Cliente final - futuro]
  end

  subgraph phoenix [Sistema Phoenix]
    NP[Plataforma Phoenix]
  end

  subgraph external [Sistemas externos - opcionales]
    PAC[Proveedor fiscal PAC]
    LLM[Proveedores LLM]
    OBJ[Almacenamiento objetos]
    MAIL[Email]
  end

  ADM -->|Configura catálogos formularios reportes flujos| NP
  SUP -->|Valida rutinas y evidencias| NP
  TEC -->|Ejecuta rutinas móvil fase 3| NP
  FAC -->|Emite facturas| NP
  CLI -.->|Consulta reportes| NP

  NP --> OBJ
  NP --> LLM
  NP --> MAIL
  NP --> PAC
```

## Objetivo en una línea

Centralizar **operación de mantenimiento**, **documentación configurable** y **cobro**, con campo offline y IA acotada a asistencia.

Ver [project-intent.md](../vision/project-intent.md).
