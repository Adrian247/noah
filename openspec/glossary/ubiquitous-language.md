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
| **Tipo de rutina** | Plantilla configurable: formulario + reporte + workflow (+ reglas) y **línea de servicio**. |
| **Línea de servicio** | Clasificación del tipo de rutina: `maintenance` (Mantenimiento), `fabrication` (Manufactura), `supply` (Suministro). |
| **Manufactura** | Línea de trabajo productivo o de obra para un **cliente** (estructuras, obra civil, textiles, bordados, diseño, etc.). El **tipo de rutina** concreta el oficio; la línea no asume un producto fijo. Código API: `fabrication`. |
| **Rutina** | Instancia de trabajo asociada a un tipo de rutina. Mantenimiento exige **activo**; manufactura/suministro exigen **cliente** (activo opcional). **Agregado principal** (`Routine`). |
| **Suministro** | Servicio de compra/reventa de insumos a un cliente (≠ **Proveedor** del catálogo maestro). |
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

## Predictivo

| Término | Definición |
|---------|------------|
| **Bitácora de turno** | Renglón por activo, día y turno con horas, consumos y horómetro (`EquipmentShiftLog`). |
| **Episodio de falla** | Una indisponibilidad completa, aunque abarque varios turnos (`EquipmentFailure`). Contar renglones en lugar de episodios infla la tasa. |
| **Modo de falla** | Clasificación canónica de la avería, con síntomas, causas y señales que la anticipan (`FailureMode`). |
| **Clase de equipo** | Clase funcional canónica: `SCOOPTRAM`, `JUMBO`, `QUEBRADORA`… Los alias de piso (`SS`, `scoop`, `LHD`) se resuelven a ella. |
| **Ventana de predicción** | Días hacia adelante que cubre la predicción (7, 14 o 30). |
| **Fallas esperadas** | Valor esperado de fallas en la ventana. Es lo que define el nivel de riesgo. |
| **Probabilidad** | P(al menos una falla en la ventana). Se reporta, pero no ordena: se satura en flotas de alta tasa. |
| **Factor / driver** | Cada evidencia observable que sube el riesgo sobre la línea base, con su aporte relativo. |
| **Precursor** | Código de alarma de máquina que antecede a un modo de falla. |
| **Confianza** | Cuánto historial respalda la predicción; no es su probabilidad. |
| **Cobertura** | Periodo observable de un activo para etiquetar entrenamiento: historial de rutinas aplicadas o, en corpus de referencia, bitácora. Sin cobertura futura no hay etiqueta posible. |

## Seguridad

| Término | Definición |
|---------|------------|
| **Rol** | Conjunto nombrado de permisos (Administrador, Supervisor, Técnico, …). |
| **Permiso** | Capacidad atómica (p. ej. `routines.validate`, `reports.design`). |

## Términos a evitar en código interno

- Usar un solo agregado **`Routine`** en MVP; no crear `WorkOrder` en paralelo salvo ADR nueva.
- En UI en español puede decirse “orden de trabajo” como etiqueta, mapeando siempre a `Routine`.
- No usar “Mantix”; el producto es **Phoenix**.
