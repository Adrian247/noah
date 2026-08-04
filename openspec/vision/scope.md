# Alcance — Phoenix

## Qué es este proyecto

- **Greenfield**: nuevo producto, nuevo repositorio, nuevo dominio.
- Fase actual del repo: **conceptualización y diseño** (`openspec/`, sin aplicación en producción).
- Referencias a otros trabajos pasados son **patrones y lecciones**, no dependencias técnicas.

## Dentro de alcance (por fases)

### Fase 1 — Plataforma web

- Multi-empresa preparada en modelo (SaaS puede activarse después).
- Catálogos: equipos, insumos, costos asociados.
- Mantenimiento / servicios definidas por **esquema de formulario**.
- Editor de **plantillas de reporte** y generación PDF.
- Módulo de **facturación** separado del dominio de mantenimiento (adaptadores fiscales por país).
- Usuarios, roles y permisos granulares.
- Auditoría de acciones relevantes.

### Fase 2 — Móvil y sincronización

- App Flutter: servicios, fotos, tiempos, comentarios, firma.
- Almacenamiento local + cola de envío.
- API de sincronización por **eventos** (no réplica de tablas completas).
- Evidencias en object storage; BD solo metadatos.

### Fase 3 — Madurez de plataforma

- Workflow designer completo, rule engine, dashboards configurables.
- AI Gateway con registro de prompts y herramientas internas.
- Notificaciones multicanal.

## Fuera de alcance (inicial)

- Microservicios desde el día uno.
- Plantillas Word como motor principal de reportes.
- IA que genere contenido factual no presente en la captura.
- Integración fiscal específica hasta definir país y proveedor PAC (se diseña interfaz, no implementación prematura).
- Migración o sincronización con bases de datos de **otros productos** (no hay cruce por diseño).

## Integraciones futuras

Cualquier conexión con ERP, CRM, LIMS u otros sistemas se documentará en `architecture/integrations.md` con contrato explícito. Hasta entonces, Phoenix es **sistema maestro** de su propio dominio operativo.

## Criterio de éxito de la fase documental

- Equipo alineado en lenguaje ([glosario](../glossary/ubiquitous-language.md)).
- Arquitectura y motores descritos con diagramas.
- Diseño de información y journeys acordados antes del primer sprint de código.
