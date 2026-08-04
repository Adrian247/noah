# Bounded contexts — Phoenix

Cada contexto tiene su agregado principal, lenguaje y límites. La comunicación entre contextos es por **eventos de integración** o **servicios de aplicación** explícitos.

| Contexto | Responsabilidad | Agregados principales (borrador) |
|----------|-----------------|----------------------------------|
| **Identity** | Autenticación, usuarios, roles base | User, Role |
| **Companies** | Tenants, sitios, configuración org | Company, Site |
| **Assets** | Activos instalados | Asset |
| **Catalogs** | Maestros reutilizables | EquipmentCatalog, Supplier |
| **Inventory** | Insumos, stock, costos unitarios | SupplyItem, StockMovement |
| **Dynamic Forms** | Definiciones y versiones de formularios | FormDefinition, FormVersion |
| **Dynamic Reports** | Plantillas y render | ReportTemplate, ReportComponent |
| **Maintenance** | Servicios, ejecuciones, evidencias (refs) | RoutineType, Routine, Execution |
| **Workflow** | Definiciones y instancias de flujo | WorkflowDefinition, WorkflowInstance |
| **Billing** | Facturas, líneas, estados fiscales | Invoice, InvoiceLine |
| **AI Gateway** | Prompts, invocaciones, auditoría | PromptVersion, AIRequest |
| **Synchronization** | Dispositivos, eventos de sync | Device, SyncBatch |
| **Storage** | Metadatos de archivos | StoredFile |
| **Notifications** | Plantillas y envíos | Notification |
| **Audit** | Registro append-only | AuditEntry |

## Reglas de frontera

1. **Billing** no lee tablas de Maintenance directamente; reacciona a eventos con payload acordado.
2. **Dynamic Reports** recibe datos de lectura (DTO) desde Maintenance al generar PDF.
3. **AI Gateway** no persiste ejecuciones de servicio; solo texto de entrada/salida y metadatos de la petición.
4. **Synchronization** traduce eventos de dominio a/from protocolo móvil; no contiene reglas de negocio de Maintenance.

## Contexto unificado vs. microservicios

Todos los contextos viven en el **mismo despliegue** (monolito modular) con namespaces o módulos Laravel claros (`app/Domain/Maintenance`, etc.) — detalle de implementación en fase de código.
