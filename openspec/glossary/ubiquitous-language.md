# Lenguaje ubicuo — Phoenix

Glosario acordado entre negocio, diseño y desarrollo. Usar estos términos en UI (salvo localización), API y documentación.

## Organización

| Término | Definición |
|---------|------------|
| **Empresa (Tenant)** | Organización que usa Phoenix; en SaaS, unidad de aislamiento de datos. |
| **Sucursal / Sitio** | Ubicación física o lógica donde hay activos y rutinas. |
| **Usuario** | Persona con cuenta; tiene uno o más roles en una empresa. |

## Activos y catálogos

| Término | Definición |
|---------|------------|
| **Activo** | Equipo industrial instalado en un sitio (instancia). |
| **Catálogo de equipo** | Definición de familia/modelo reutilizable (especificaciones, documentos). |
| **Insumo** | Material consumible o refacción con costo y unidad. |
| **Proveedor** | Tercero que suministra insumos o servicios. |

## Operación

| Término | Definición |
|---------|------------|
| **Tipo de rutina** | Plantilla configurable: formulario + reporte + workflow (+ reglas). |
| **Rutina** | Instancia de trabajo de campo o taller asociada a un activo y un tipo de rutina. **Agregado principal en MVP** (`Routine`). |
| **Orden de trabajo** | Término de negocio opcional en UI; en dominio MVP es **sinónimo de Rutina**. Reservado para fase posterior si se separa planificación (backlog) de ejecución (campo). |
| **Mantenimiento** | Concepto amplio de intervención; en documentación suele referirse al bounded context, no a una tabla. |
| **Evidencia** | Fotografía, firma, archivo o dato que prueba la ejecución. |
| **Ejecución** | Conjunto de respuestas de formulario + evidencias + tiempos de una rutina. |

## Documentos

| Término | Definición |
|---------|------------|
| **Plantilla de reporte** | Diseño versionado (componentes JSON) para generar PDF. |
| **Componente de reporte** | Bloque atómico (título, tabla, galería, pie, etc.). |
| **Reporte generado** | PDF (u otro formato) producido a partir de plantilla + datos de ejecución. |

## Formularios

| Término | Definición |
|---------|------------|
| **Definición de formulario** | Metadatos: secciones, campos, validaciones, permisos. |
| **Campo** | Elemento de captura (texto, número, fecha, lista, foto, firma, …). |
| **Publicación** | Versión activa de formulario o plantilla consumida por web/móvil. |

## Flujo y calidad

| Término | Definición |
|---------|------------|
| **Workflow** | Secuencia configurable de estados, aprobaciones y acciones. |
| **Validación** | Acción de supervisor (u otro rol) que aprueba o rechaza una ejecución. |
| **Corrección IA** | Paso automático que mejora redacción sin alterar hechos. |

## Economía

| Término | Definición |
|---------|------------|
| **Costo de mantenimiento** | Suma registrada de insumos, tiempos y cargos configurados. |
| **Factura** | Documento de cobro; dominio separado del mantenimiento. |
| **Borrador de factura** | Estado previo a timbrado o emisión fiscal. |

## Móvil y sync

| Término | Definición |
|---------|------------|
| **Dispositivo** | Instalación móvil identificada para sync. |
| **Cola local** | Eventos pendientes de envío en el dispositivo. |
| **Sincronización** | Intercambio idempotente de eventos servidor ↔ dispositivo. |
| **Source of truth** | El servidor; el móvil reconcilia hacia él. |

## Seguridad

| Término | Definición |
|---------|------------|
| **Rol** | Conjunto nombrado de permisos (Administrador, Supervisor, Técnico, …). |
| **Permiso** | Capacidad atómica (p. ej. `routines.validate`, `reports.design`). |

## Términos a evitar en código interno

- Usar un solo agregado **`Routine`** en MVP; no crear `WorkOrder` en paralelo salvo ADR nueva.
- En UI en español puede decirse “orden de trabajo” como etiqueta, mapeando siempre a `Routine`.
- No usar “Mantix”; el producto es **Phoenix**.
