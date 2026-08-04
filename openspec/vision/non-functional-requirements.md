# Requisitos no funcionales — Phoenix

## Disponibilidad

| Área | Objetivo inicial | Notas |
|------|------------------|-------|
| API web | 99.5% horario laboral (piloto) | Mejorar en SaaS |
| Generación PDF | Best-effort con reintentos en cola | No bloquear HTTP |
| Sync móvil | Disponible cuando hay red | Offline no requiere API |

## Rendimiento (objetivos de diseño)

| Operación | Objetivo |
|-----------|----------|
| Listado servicios (paginado) | < 500 ms p95 API |
| Guardar ejecución web | < 300 ms p95 |
| Vista previa reporte (HTML) | < 2 s p95 |
| PDF async | Usuario notificado; < 60 s p95 job |
| Sync lote móvil | Progresivo; fotos en paralelo limitado |

## Escalabilidad

- Stateless API detrás de load balancer (fase producción).
- Colas para PDF, IA, email.
- Object storage para evidencias.
- PostgreSQL vertical primero; réplica lectura si hace falta.

## Seguridad

- TLS en tránsito; secretos en variables de entorno.
- Aislamiento por `company_id` (ADR-009).
- Auditoría en acciones sensibles.
- IA: sin enviar datos a terceros sin política de retención acordada.

## Mantenibilidad

- Monolito modular con límites documentados.
- OpenSpec + ADRs obligatorios antes de cambios arquitectónicos.
- Cobertura de tests: dominio crítico y motores en fase de código (meta ≥ 70% en módulos core, a definir en CI).

## Usabilidad

- Admin: WCAG 2.1 AA en flujos principales.
- Móvil: operable con una mano; estados sync legibles.

## Portabilidad

- Docker para dev y prod.
- Storage y LLM intercambiables por configuración.

## Cumplimiento

- Facturación: según legislación del adaptador fiscal elegido (fuera del núcleo).
- Retención de datos y borrado: política por empresa (futuro).

## Observabilidad

Ver [architecture/observability.md](../architecture/observability.md).
