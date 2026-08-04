# Dominio — Notifications (Phoenix)

Motor: [notification-engine.md](../architecture/notification-engine.md).

## Agregados

### NotificationTemplate

- Canal (email v1 / push v2), asunto, cuerpo con variables (`{{routine_id}}`, `{{supervisor_name}}`).

### Notification

- Destinatario, canal, estado (queued, sent, failed), payload, correlación con evento origen.

### DevicePushToken

- Token FCM por usuario + `device_id` + plataforma (`android` | `ios`).
- Baja al logout o token inválido.

## Disparadores (ejemplos)

- Servicio asignado → técnico (email + push).
- Pendiente validación → supervisor (email + push).
- Transiciones workflow con `notify` → mismos destinatarios.
- Reporte listo → facturación o cliente.
- Error sync → administrador (futuro).

## Invariantes

- Envío async; reintentos con backoff.
- Preferencias de usuario respetadas cuando existan (fase posterior).
- Push no bloquea el request HTTP; fallos se registran sin tumbar el workflow.

## Eventos

- `NotificationQueued`, `NotificationSent`, `NotificationFailed`
