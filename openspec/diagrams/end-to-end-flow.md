# Flujo end-to-end — Noah

Desde configuración hasta entrega al cliente (visión objetivo completa).

```mermaid
flowchart TD
  subgraph config [Configuración - Admin]
    A1[Crear formulario]
    A2[Crear plantilla reporte]
    A3[Definir workflow]
    A4[Publicar tipo de rutina]
    A1 --> A4
    A2 --> A4
    A3 --> A4
  end

  subgraph field [Campo - Técnico]
    B1[Asignar rutina]
    B2[Ejecutar captura evidencias tiempos]
    B3[Finalizar local / sync]
    B1 --> B2 --> B3
  end

  subgraph office [Oficina]
    C1[IA corrige redacción]
    C2[Supervisor valida]
    C3[Generar PDF]
    C4[Borrador factura]
    C5[Emitir factura]
    C1 --> C2 --> C3 --> C4 --> C5
  end

  A4 --> B1
  B3 --> C1
  C3 --> ENT[Entrega reporte + factura]
```

## Notas por fase

| Tramo | Fase roadmap |
|-------|----------------|
| Config + web sin móvil | 1–2 |
| IA gramatical + workflow | 2 |
| Móvil offline + sync | 3 |
| Entrega fiscal real | 2+ según adaptador país |

Journey detallado: [personas-and-journeys.md](../design/personas-and-journeys.md).
