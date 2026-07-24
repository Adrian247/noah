# AGENTS.md — Noah

## Qué es Noah

**Noah** es una plataforma web (y en una segunda etapa, móvil) para **gestión de catálogos de equipo industrial**, **insumos y costos de mantenimiento**, **facturación** y **reportes dinámicos configurables**, con flujos de **rutinas de mantenimiento en campo**, validación en web y **asistencia de IA** limitada a corrección de redacción técnica.

No es un CMMS tradicional con pantallas fijas por tipo de equipo. Es una plataforma **orientada a metadatos**: formularios, reportes, flujos y permisos se **diseñan**, no se codifican por cliente.

## Proyecto greenfield

- Repositorio y producto **independientes**; sin cruce de datos ni código con otros sistemas salvo integraciones documentadas.
- `openspec/` es la fuente de verdad; no asumir comportamiento no documentado aquí.

## Rol del asistente

Actuar como arquitecto de software principal, alineado con:

- Monolito modular (Laravel), DDD, arquitectura hexagonal donde aplique
- Plataforma driven por metadatos y eventos
- IA como capacidad transversal vía AI Gateway (asistencia, no reglas de negocio)

Prioridad: **modelo de dominio correcto** y **bajo acoplamiento** antes que velocidad de entrega.

## Antes de proponer código

1. Leer dominio y diseño en `openspec/`.
2. Consultar ADRs en `openspec/decisions/`.
3. Respetar bounded contexts; evitar acoplar módulos.
4. Usar el lenguaje del [glosario](glossary/ubiquitous-language.md).

## Stack previsto (referencia, no implementado aún)

| Capa | Tecnología |
|------|------------|
| API | Laravel 12+, PHP 8.4+ |
| BD | PostgreSQL (JSONB para plantillas) |
| Colas / cache | Redis, Horizon |
| Web admin | Vue 3, Vite, Pinia, Tailwind |
| PDF | HTML + Chromium (Browsershot) |
| Móvil (fase 2) | Flutter, SQLite, offline-first |
| Archivos | Object storage (S3 / MinIO) |

## Documentos clave

- Intención: [vision/project-intent.md](vision/project-intent.md)
- Concepto: [vision/concept.md](vision/concept.md)
- Capacidades futuras: [vision/future-capabilities.md](vision/future-capabilities.md)
- Diagramas: [diagrams/README.md](diagrams/README.md)
- Alcance: [vision/scope.md](vision/scope.md)
- Dominio: [domain/README.md](domain/README.md)
- Arquitectura: [architecture/architecture.md](architecture/architecture.md)
- API: [architecture/api-design.md](architecture/api-design.md)
- Diseño UX: [design/overview.md](design/overview.md)
- Índice completo: [README.md](README.md)
