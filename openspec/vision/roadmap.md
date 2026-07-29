# Roadmap — Phoenix

Roadmap orientativo; las fechas se fijan al iniciar implementación.

## Fase 0 — Fundación documental

- [x] Estructura `openspec/` y nombre Phoenix
- [x] Visión, alcance, concepto, roadmap, negocio, NFR
- [x] Dominio por contexto, arquitectura, motores, API
- [x] Diseño UX, frontend, móvil, infra, prompts, diagramas
- [x] ADRs 001–010
- [x] Flujo de cambios (`openspec/changes/` → `archive/`)
- [ ] Revisión formal de stakeholders del glosario y journeys (opcional continuo)

## Fase 1 — Núcleo web (MVP operativo)

**Estado:** **completado** en código (web MVP operativo).

**Objetivo:** operar mantenimientos y reportes desde web sin móvil.

| Entrega | Descripción |
|---------|-------------|
| Identity & Companies | Usuarios, empresas, permisos base |
| Assets & Catalogs | Equipos, insumos, proveedores |
| Forms Engine v1 | Definir campos y versiones; captura web |
| Report Engine v1 | Componentes básicos + PDF |
| Maintenance v1 | Rutina / orden ligada a formulario y activo |
| Inventory & costs | Consumo de insumos y registro de costos |
| Audit básico | Quién cambió qué en entidades críticas |

**No incluye aún:** móvil nativo, facturación fiscal en producción.

## Fase 2 — Flujo de negocio completo (web)

**Estado:** **completado** (workflow, IA gramatical, billing borrador, email, diseñadores MVP).

| Entrega | Descripción |
|---------|-------------|
| Workflow Engine v1 | Validación supervisor, estados |
| AI Gateway v1 | Corrección gramatical en paso de workflow |
| Billing v1 | Borrador de factura; interfaz fiscal abstracta |
| Report designer v1 | UI tipo Canva (MVP) |
| Notifications v1 | Email mínimo |

## Fase 3 — Campo y sincronización

**Estado:** **servidor v1 listo** (API sync + evidencias); **cliente Flutter pendiente**.

| Entrega | Descripción |
|---------|-------------|
| Flutter app | Rutinas asignadas, captura offline — ver `mobile/README.md` |
| Mobile Sync | `POST /api/v1/sync` eventos idempotentes |
| Storage | Evidencias en disco local; MinIO preparado en compose |

## Fase 4 — Plataforma y capacidades avanzadas

- Multitenancy SaaS endurecido
- Rule engine y dashboards configurables
- Integraciones documentadas
- Capacidades IA, visión y analítica según [future-capabilities.md](future-capabilities.md) (OCR, reconocimiento equipo, fugas/corrosión, chatbot, predicción, costos, refacciones, etc.)

Diagrama: [../diagrams/future-ai-vision.md](../diagrams/future-ai-vision.md).

## Dependencias entre fases

```mermaid
flowchart LR
  F0[Fase 0 Docs] --> F1[Fase 1 Web MVP]
  F1 --> F2[Fase 2 Workflow + IA + Billing]
  F2 --> F3[Fase 3 Móvil]
  F2 --> F4[Fase 4 Plataforma]
  F3 --> F4
```
