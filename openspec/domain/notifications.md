# Dominio — Notifications (Noah)

Motor: [notification-engine.md](../architecture/notification-engine.md).

## Agregados

### NotificationTemplate

- Canal (email v1), asunto, cuerpo con variables (`{{routine_id}}`, `{{supervisor_name}}`).

### Notification

- Destinatario, canal, estado (queued, sent, failed), payload, correlación con evento origen.

## Disparadores (ejemplos)

- Rutina asignada → técnico.
- Pendiente validación → supervisor.
- Reporte listo → facturación o cliente.
- Error sync → administrador (futuro).

## Invariantes

- Envío async; reintentos con backoff.
- Preferencias de usuario respetadas cuando existan (fase posterior).

## Eventos

- `NotificationQueued`, `NotificationSent`, `NotificationFailed`
