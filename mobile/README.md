# Noah — App de campo (Fase 3)

El cliente móvil **Flutter offline-first** está planificado en `openspec/mobile/` y `openspec/design/mobile-field-app.md`.

## Contrato servidor (implementado)

- `POST /api/v1/sync` con `device_id`, `events[]` (push) y `pull` (catálogo de rutinas asignadas).
- Evento soportado: `execution.submitted` (idempotente por `event_id`).
- Evidencias: `POST /api/v1/routines/{routine}/evidences` (multipart).

## Próximo paso Flutter

1. Proyecto Flutter con SQLite local y cola de eventos.
2. Pantallas: rutinas asignadas, captura de formulario dinámico, cola de sync.
3. Usar la guía **docs/PRUEBAS_MANUALES.md** sección F para probar el backend sin app.
