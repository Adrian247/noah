# Phoenix — App de campo (Flutter)

Cliente móvil **offline-first** para técnicos: rutinas asignadas, formularios dinámicos, cola de sync y envío de ejecuciones.

Documentación: `openspec/mobile/`, `openspec/design/mobile-field-app.md`.

## Stack

| Capa | Tecnología |
|------|------------|
| UI | Flutter 3.x |
| Estado | Riverpod |
| HTTP | Dio + Sanctum |
| Local | Drift (SQLite) |
| Sync | `POST /api/v1/sync` |

## Estructura

```
mobile/phoenix_field/
  lib/
    core/          # config, theme, router, red
    data/          # API, SQLite, repos, sesión
    features/      # auth, rutinas, sync, perfil
    shared/        # renderer de formulario dinámico
```

## Requisitos

- Flutter SDK 3.22+ (`flutter doctor`)
- Backend Phoenix corriendo (Docker: `http://localhost:8888`)

## Arranque rápido

### 1. Ubicación correcta

Desde la raíz del repo **noah**:

```bash
cd mobile/phoenix_field
```

Si tu prompt ya dice `phoenix_field`, **no vuelvas a hacer `cd mobile/phoenix_field`** (esa ruta no existe desde ahí). Comprueba que estás en el proyecto con:

```bash
ls pubspec.yaml
```

### 2. Flutter en PATH (ya configurado en este equipo)

El SDK está en `~/flutter` y quedó registrado en el sistema:

- `/etc/profile.d/flutter.sh` — PATH para shells de login
- `/usr/local/bin/flutter` y `/usr/local/bin/dart` — symlinks
- `~/.zshrc` — PATH para zsh

Abre una **terminal nueva** o ejecuta:

```bash
source ~/.zshrc
flutter doctor
```

Deberías ver **Linux toolchain ✓**. Android Studio/SDK es opcional (para emulador); en desktop usa:

```bash
flutter run -d linux
```

Si en otra máquina no tienes Flutter:

```bash
git clone https://github.com/flutter/flutter.git -b stable --depth 1 ~/flutter
echo 'export PATH="$HOME/flutter/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
flutter doctor
```

### 3. Correr la app

```bash
cd mobile/phoenix_field   # solo si vienes de la raíz del repo
chmod +x tool/dev.sh
./tool/dev.sh run
```

O manualmente:

```bash
flutter pub get
dart run build_runner build
flutter run
```

### URL de API por plataforma

| Plataforma | URL por defecto |
|------------|-----------------|
| **Linux / macOS / Windows** | `http://localhost:8888/api/v1` |
| Emulador Android | `http://10.0.2.2:8888/api/v1` |
| Dispositivo físico | IP de tu máquina, ej. `http://192.168.1.10:8888/api/v1` |

También puedes cambiarla en la pantalla de login o con:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8888/api/v1
```

## Credenciales demo

Técnico Mein Company:

- Email: `misael.palos@mein-company.com`
- Contraseña: `pyro.2026$` (demo actual)

Ver `docs/DEMO_ENV.md` y `docs/PRUEBAS_MANUALES.md` (sección F) para probar el backend sin app.

## Push notifications (FCM)

Phoenix Campo registra el token del dispositivo en `POST /api/v1/mobile/device-tokens` tras el login. El backend envía push (además del correo) al asignar rutinas y en otros avisos de workflow.

### Backend

```env
PHOENIX_PUSH_ENABLED=true
PHOENIX_PUSH_DRIVER=log   # local/tests
# PHOENIX_PUSH_DRIVER=fcm
# FCM_PROJECT_ID=tu-proyecto
# FCM_CREDENTIALS=/ruta/al/service-account.json
```

Con `QUEUE_CONNECTION=redis` hace falta un worker (`php artisan queue:work`).

### App

1. Crea un proyecto en [Firebase Console](https://console.firebase.google.com/) y añade la app Android `com.pyrosystems.phoenix_field` (e iOS si aplica).
2. Sustituye `android/app/google-services.json` por el descargado (el del repo es placeholder).
3. En el servidor, usa un service account con rol **Firebase Cloud Messaging API Admin** y `PHOENIX_PUSH_DRIVER=fcm`.
4. iOS: configura APNs en Firebase y añade `GoogleService-Info.plist`.

Sin proyecto Firebase real, la app compila pero no obtendrá token FCM; el backend con driver `log` deja traza en logs al disparar avisos.

## Flujo v0.7

1. Todo lo de v0.6, más:
2. **Cronómetro auto-inicio** al abrir una rutina en estado `assigned` (tiempo en sitio)
3. Branding Phoenix (logo, animación de entrada, icono de app)
4. **PIN/biometría** persistente por usuario; bloqueo solo al volver del segundo plano
5. **APK release** estable (ProGuard WorkManager)

## Flujo v0.6

1. Todo lo de v0.5, más:
2. **Filtro «Hoy»** por `scheduled_at` (segmento Hoy / Todas)
3. **Campo `options` como radio**; `select` sigue en dropdown
4. **Captions en fotos** cuando el formulario lo habilita
5. **Validaciones avanzadas** (min/max numérico, longitud, patrón, catálogos, captions)
6. **Consumos / insumos** en ejecución (catálogo vía sync pull)

## Flujo v0.5

1. Todo lo de v0.4, más:
2. **Logout con limpieza**: SQLite, cola outbox, borradores y archivos locales (`media/`)
3. **Política móvil por empresa** (web → Configuración → App móvil): PIN obligatorio y biometría permitida o no
4. **APK release firmado** con keystore propio (ver abajo)

## Flujo v0.4

1. Login → token Sanctum + `X-Company-Id`
2. Pull de rutinas asignadas y catálogos de opciones
3. Captura de formulario dinámico (texto, número, select, multiselect, duration, **boolean**, **date**, **datetime**, fotos)
4. **Cronómetro** de tiempo en sitio
5. **Firma** al finalizar
6. Envío local → **compresión de fotos** → subida → cola outbox → push `execution.submitted`
7. Pantalla **Cola** con eventos y medios pendientes
8. Banner global de estado de sync
9. **Sync en segundo plano** (Android/iOS, cada ~15 min con red)
10. **Cámara nativa** en móvil (galería/archivo en desktop)
11. **Bloqueo con PIN** y **biometría** opcional (Perfil → Seguridad)
12. **Selector de empresa** en Perfil (multi-tenant; limpia caché local al cambiar)

## Build release (APK firmado)

1. Genera un keystore (una sola vez; **no lo subas al repo**):

```bash
mkdir -p mobile/phoenix_field/android/keystore
keytool -genkey -v \
  -keystore mobile/phoenix_field/android/keystore/phoenix-field.jks \
  -alias phoenix-field -keyalg RSA -keysize 2048 -validity 10000
```

2. Copia y edita credenciales:

```bash
cp mobile/phoenix_field/android/key.properties.example mobile/phoenix_field/android/key.properties
```

3. Compila:

```bash
cd mobile/phoenix_field
chmod +x tool/build_release.sh
./tool/build_release.sh
```

Sin `key.properties`, `flutter build apk --release` usa firma debug (solo pruebas locales).

## Flujo v0.3 (base Fase 2)

Ver commits anteriores; v0.4 añade paridad de campos, compresión y selector de empresa.

## Pendiente (siguientes iteraciones)

- Distribución AAB / Play Store interna
- Reordenar fotos en galería móvil

> `duration_minutes` en reporte PDF: formato hh:mm implementado en servidor (ReportHtmlBuilder).

## Contrato servidor

- `POST /api/v1/auth/login`
- `POST /api/v1/sync` (`device_id`, `events[]`, `pull`)
- Evento: `execution.submitted` (idempotente por `event_id`)
