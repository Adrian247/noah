# ADR-014 — Push móvil vía FCM

## Estado

Aceptada

## Contexto

Phoenix Campo necesita notificaciones del sistema (asignación de rutina, etc.). El correo no sustituye el aviso en el teléfono.

## Decisión

- Canal **Firebase Cloud Messaging (HTTP v1)** detrás de un `PushNotifier` con drivers `log` | `fcm`.
- Tokens por **usuario + device_id** (el UUID ya usado en sync).
- Disparadores alineados con destinatarios del workflow (no un canal paralelo de reglas).
- Credenciales solo en servidor (`FCM_CREDENTIALS` / project id); la app usa `google-services.json` / `GoogleService-Info.plist`.

## Consecuencias

- Sin proyecto Firebase configurado, el backend usa `log` y el móvil puede no obtener token real.
- iOS requiere APNs enlazado en Firebase Console.
