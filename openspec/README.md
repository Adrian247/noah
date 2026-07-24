# Noah — Especificación del producto

**Noah** es un proyecto **nuevo y autónomo**. Esta carpeta (`openspec/`) es la **única fuente de verdad** para visión, arquitectura, dominio y diseño.

## Alcance de este repositorio

- Documentación de producto, arquitectura y diseño.
- **Scaffold de aplicación:** Laravel 13 + Vue 3 + Docker (ver [README.md](../README.md) y [docs/IMPLEMENTATION.md](../docs/IMPLEMENTATION.md)).

## Relación con otros sistemas

Noah **no comparte** dominio de datos, despliegue ni código con otros productos. Se pueden reutilizar **ideas y patrones** aprendidos en otros trabajos; integraciones futuras en [architecture/integrations.md](architecture/integrations.md).

## Índice rápido

| Área | Entrada |
|------|---------|
| Contrato IA / dev | [AGENTS.md](AGENTS.md) |
| Concepto | [vision/concept.md](vision/concept.md) |
| Glosario | [glossary/ubiquitous-language.md](glossary/ubiquitous-language.md) |
| Dominio | [domain/README.md](domain/README.md) |
| Diseño UX | [design/overview.md](design/overview.md) |
| ADRs | [decisions/README.md](decisions/README.md) |
| Diagramas | [diagrams/](diagrams/) |

## Visión

| Documento | Tema |
|-----------|------|
| [product.md](vision/product.md) | Qué es Noah |
| [concept.md](vision/concept.md) | Modelo mental y flujos |
| [scope.md](vision/scope.md) | Greenfield, fases, límites |
| [principles.md](vision/principles.md) | Principios de diseño |
| [roadmap.md](vision/roadmap.md) | Fases 0–4 |
| [business-model.md](vision/business-model.md) | Valor y SaaS |
| [target-users.md](vision/target-users.md) | Roles y permisos |
| [non-functional-requirements.md](vision/non-functional-requirements.md) | NFR |
| [success-metrics.md](vision/success-metrics.md) | KPIs |
| [project-intent.md](vision/project-intent.md) | Intención resumida |
| [future-capabilities.md](vision/future-capabilities.md) | IA, visión, analítica (futuro) |

## Arquitectura

| Documento | Tema |
|-----------|------|
| [architecture.md](architecture/architecture.md) | Vista general |
| [modules.md](architecture/modules.md) | Módulos |
| [context-map.md](architecture/context-map.md) | Context map |
| [bounded-contexts.md](architecture/bounded-contexts.md) | Límites DDD |
| [domain-events.md](architecture/domain-events.md) | Eventos |
| [api-design.md](architecture/api-design.md) | REST |
| [versioning.md](architecture/versioning.md) | API y metadatos |
| [multitenancy.md](architecture/multitenancy.md) | Tenants |
| [security.md](architecture/security.md) | Seguridad |
| [storage.md](architecture/storage.md) | Archivos |
| [caching.md](architecture/caching.md) | Cache |
| [observability.md](architecture/observability.md) | Logs y métricas |
| [integrations.md](architecture/integrations.md) | Externos |
| [forms-engine.md](architecture/forms-engine.md) | Motor formularios |
| [report-engine.md](architecture/report-engine.md) | Motor reportes |
| [workflow-engine.md](architecture/workflow-engine.md) | Motor workflow |
| [rules-engine.md](architecture/rules-engine.md) | Reglas |
| [notification-engine.md](architecture/notification-engine.md) | Notificaciones |
| [ai-gateway.md](architecture/ai-gateway.md) | IA |
| [mobile-sync.md](architecture/mobile-sync.md) | Sync servidor |

## Diseño

| Documento | Tema |
|-----------|------|
| [overview.md](design/overview.md) | Objetivos UX |
| [information-architecture.md](design/information-architecture.md) | Navegación web |
| [personas-and-journeys.md](design/personas-and-journeys.md) | Personas |
| [report-designer.md](design/report-designer.md) | Diseñador PDF |
| [form-designer.md](design/form-designer.md) | Diseñador formularios |
| [mobile-field-app.md](design/mobile-field-app.md) | App campo |
| [design-system.md](design/design-system.md) | Tokens y componentes |

## Frontend / móvil / infra

| Carpeta | Documentos |
|---------|------------|
| [frontend/](frontend/) | vue, ui, navigation, accessibility |
| [mobile/](mobile/) | flutter, offline, synchronization |
| [infrastructure/](infrastructure/) | docker, deployment, environments, backups, monitoring |
| [prompts/](prompts/) | grammar-correction, prompt-guidelines |

## Diagramas

Índice: [diagrams/README.md](diagrams/README.md) — contexto, contenedores, capas, flujos E2E, diseño, roadmap, IA futura.

## Origen

Intención inicial destilada en [vision/project-intent.md](vision/project-intent.md). El archivo raíz [../intention.md](../intention.md) solo enlaza a `openspec/`.
