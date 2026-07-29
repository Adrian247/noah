# Docker — Phoenix

Entorno de desarrollo y base para producción. Sin código aún; servicios previstos.

## Compose (desarrollo)

| Servicio | Imagen / build | Puerto |
|----------|----------------|--------|
| `app` | PHP-FPM Laravel | — |
| `web` | Nginx | 8080 |
| `postgres` | postgres:16 | 5432 |
| `redis` | redis:7 | 6379 |
| `minio` | minio | 9000 |
| `mailpit` | axllent/mailpit | 8025 |
| `node` | Vite dev (opcional) | 5173 |

## Volúmenes

- `postgres_data`, `minio_data`, código montado en `app`.

## Workers

- Contenedor `queue` con `php artisan horizon`.
- Contenedor `scheduler` con cron o `schedule:work`.

## Producción

- Imágenes multi-stage: composer install `--no-dev`, assets build de Vue.
- Secrets vía env, no en imagen.

Ver [deployment.md](deployment.md).
