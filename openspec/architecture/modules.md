# Módulos — Phoenix

Lista de módulos lógicos del monolito modular.

| Módulo | Descripción breve |
|--------|-------------------|
| **Core** | Arranque, convenciones, shared kernel mínimo |
| **Identity** | Usuarios, autenticación, roles |
| **Companies** | Tenants, sitios |
| **Assets** | Activos instalados |
| **Catalogs** | Maestros de equipo, proveedores |
| **Inventory** | Insumos, stock, costos |
| **Maintenance** | Servicios, ejecuciones, evidencias (refs) |
| **Dynamic Forms** | Definiciones de formulario |
| **Dynamic Reports** | Plantillas y generación PDF |
| **Workflow Engine** | Flujos y estados |
| **Notification Engine** | Email y canales futuros |
| **Billing** | Facturación desacoplada |
| **AI Gateway** | LLM y prompts |
| **Audit** | Trazabilidad |
| **Storage** | Abstracción de archivos |
| **Synchronization** | Protocolo móvil |
| **Observability** | Logs, métricas |
| **Administration** | Configuración global UI |

Cada módulo independiente en código; ver [bounded-contexts.md](bounded-contexts.md).
