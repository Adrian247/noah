# Lenguaje ubicuo — Phoenix

Glosario acordado entre negocio, diseño y desarrollo. Usar estos términos en UI (salvo localización), API y documentación.

## Organización

| Término | Definición |
|---------|------------|
| **Empresa (Tenant)** | Organización que usa Phoenix; en SaaS, unidad de aislamiento de datos. |
| **Cliente** | Organización o persona a la que el tenant presta servicios; puede tener **n sitios** e **inventario** vinculado. |
| **Sitio** | Ubicación física o lógica del **cliente** donde hay artículos de inventario y servicios. |
| **Usuario** | Persona con cuenta; tiene uno o más roles en una empresa. |

## Artículos y catálogos

| Término | Definición |
|---------|------------|
| **Artículo (catálogo)** | Definición reutilizable de producto/equipo/estructura (`CatalogItem`). Antes: venta, fabricación, fichas técnicas. |
| **Tipo de artículo** | Clasificación del artículo con formulario de ficha asociado (`EquipmentType` / supply type en inventario). |
| **Artículos de sistema** | Plantillas del administrador de plataforma; se **importan como clones** al catálogo del tenant (sin enlace vivo). |
| **Inventario de cliente** | Instancia vinculada al artículo de catálogo en un sitio del cliente (serie, etiqueta, ubicación, OCR). Sync con catálogo salvo copia personalizada. Código: modelo `Asset`. |
| **Inventario (insumos)** | Materiales consumibles / refacciones con stock y costo (`SupplyItem`). |
| **Proveedor** | Tercero que suministra insumos o servicios. |

## Operación

| Término | Definición |
|---------|------------|
| **Tipo de servicio** | Plantilla configurable: formulario + reporte + workflow y **categoría de servicio**. Antes: tipo de rutina. |
| **Categoría de servicio** | `maintenance` (Mantenimiento), `manufacturing` (Fabricación), `installation` (Instalación). Reemplaza la antigua **línea de servicio**. |
| **Servicio** | Instancia de trabajo asociada a un tipo de servicio (`Routine`). Mantenimiento exige **artículo de inventario del cliente**; fabricación/instalación exigen **cliente**. |
| **Fabricación** | Manufactura de estructuras/artículos para un cliente; al validar, baja de materiales y costo en facturación. |
| **Instalación** | Colocación de estructuras fabricadas u otras instalaciones en sitio del cliente. |
| **Mantenimiento** | Intervención sobre artículo del inventario del cliente. |
| **Orden de trabajo** | Término de negocio opcional en UI; en dominio MVP es **sinónimo de Servicio**. |
| **Evidencia** | Fotografía, firma, archivo o dato que prueba la ejecución. |
| **Ejecución** | Conjunto de respuestas de formulario + evidencias + tiempos de un servicio. |

## Formularios

| Término | Definición |
|---------|------------|
| **Tipo de formulario** | Uso: `service` (Servicio), `article` (Artículo), `inventory` (Inventario). |
| **Definición de formulario** | Metadatos: secciones, campos, validaciones, permisos. |
| **Campo** | Elemento de captura (texto, número, fecha, lista, foto, firma, …). |
| **Publicación** | Versión activa de formulario o plantilla consumida por web/móvil. |

## Documentos

| Término | Definición |
|---------|------------|
| **Plantilla de reporte** | Diseño versionado (componentes JSON) para generar PDF. |
| **Componente de reporte** | Bloque atómico (título, tabla, galería, pie, etc.). |
| **Reporte generado** | PDF (u otro formato) producido a partir de plantilla + datos de ejecución. |

## Flujo y calidad

| Término | Definición |
|---------|------------|
| **Workflow** | Secuencia configurable de estados, aprobaciones y acciones. |
| **Validación** | Acción de supervisor (u otro rol) que aprueba o rechaza una ejecución. |
| **Corrección IA** | Paso automático que mejora redacción sin alterar hechos. |

## Economía

| Término | Definición |
|---------|------------|
| **Costo de servicio** | Suma de materiales (consumos/bajas), tiempos y cargos configurados. |
| **Factura** | Documento de cobro; dominio separado de la operación. |
| **Borrador de factura** | Creado al validar un servicio (`RoutineValidated`). |

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
| **Predicción de falla** | Estimación de riesgo sobre historial de servicios / telemetría. |
| **Demanda de cliente** | Predicción de solicitudes de fabricación o instalación. |

## Alias legacy (código)

| Legacy | Actual |
|--------|--------|
| Rutina / Routine (UI) | Servicio |
| Tipo de rutina | Tipo de servicio |
| `service_line` | `service_category` |
| Equipo / Activo (UI) | Artículo / Inventario de cliente |
| FormUsage routine/equipment/supply | service/article/inventory |
