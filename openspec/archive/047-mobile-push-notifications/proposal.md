# 047 — Push móvil (FCM)

## Contexto

El motor de notificaciones tenía email v1; push móvil estaba marcado como v2. Los técnicos en campo necesitan aviso al asignar rutina (y en otros disparadores de workflow) aunque la app esté en segundo plano.

## Alcance

| Pieza | Detalle |
|-------|---------|
| Tokens | Tabla `device_push_tokens` (user, company, device_id, platform, token FCM) |
| API | `POST/DELETE /mobile/device-tokens` (Sanctum + company) |
| Canal | `log` (default local) o `fcm` (HTTP v1 con service account) |
| Disparadores | Misma resolución de destinatarios que correo de workflow + pendiente de validación |
| Móvil | Firebase Messaging + notificaciones locales; registro tras login |

## Fuera de alcance

- Preferencias granulares por tipo de evento
- WhatsApp / SMS
- Web push

## Criterios de aceptación

1. Tras login en Campo, el dispositivo registra (o intenta registrar) un token.
2. Al crear una rutina asignada con `assignment_notify`, se encola push a los tokens del técnico (además del correo).
3. Sin `FCM_CREDENTIALS`, el driver `log` deja traza verificable en tests/local.
4. Tokens inválidos se eliminan al recibir error FCM `UNREGISTERED` / `INVALID_ARGUMENT`.
