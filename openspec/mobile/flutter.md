# Mobile — Flutter (Phoenix)

ADR: [ADR-010](../decisions/ADR-010-flutter.md). UX: [mobile-field-app.md](../design/mobile-field-app.md).

## Stack

- Flutter 3.x stable
- Estado: Riverpod o Bloc (elegir al iniciar código; documentar en README app)
- HTTP: dio
- Local: drift o sqflite
- Background sync: workmanager / flutter_background_service

## Estructura (propuesta)

```
lib/
  main.dart
  core/           # config, di, routing
  data/           # api, local db, sync
  domain/         # entities, repositories
  features/
    routines/
    sync_queue/
    auth/
  shared/
    dynamic_form/ # renderer JSON
```

## API

- Mismos endpoints que [api-design.md](../architecture/api-design.md).
- `POST /api/v1/sync` como corazón offline.

## Paridad con web

- Tipos de campo soportados = subset v1 documentado en [forms.md](../domain/forms.md).
- Versiones de esquema: rechazar ejecución si formulario no descargado.

## Distribución

- Android APK/AAB; iOS TestFlight; firma y pipelines en [deployment.md](../infrastructure/deployment.md) (fase código).
